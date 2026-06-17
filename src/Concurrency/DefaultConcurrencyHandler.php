<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Concurrency;

use BootDesk\ChatSDK\Core\Contracts\Adapter;
use BootDesk\ChatSDK\Core\Contracts\ConcurrencyHandler;
use BootDesk\ChatSDK\Core\Contracts\HasDynamicSyncPreference;
use BootDesk\ChatSDK\Core\Contracts\RequiresAsyncResponse;
use BootDesk\ChatSDK\Core\Contracts\RequiresSyncResponse;
use BootDesk\ChatSDK\Core\Contracts\StateAdapter;
use BootDesk\ChatSDK\Core\Lock;
use BootDesk\ChatSDK\Core\Message;
use BootDesk\ChatSDK\Core\QueueEntry;
use Psr\Http\Message\ServerRequestInterface;

class DefaultConcurrencyHandler implements ConcurrencyHandler
{
    /** @var array<string, int> */
    private array $concurrentSlots = [];

    public function __construct(
        private readonly StateAdapter $state,
        private readonly array $config = [],
    ) {}

    public function process(
        Adapter $adapter,
        string $threadId,
        Message $message,
        callable $processCallback,
        ?ServerRequestInterface $request = null,
    ): void {
        $strategy = Strategy::tryFrom($this->config['concurrency'] ?? 'drop') ?? Strategy::Drop;
        $debounceMs = (int) ($this->config['debounceMs'] ?? 1500);
        $maxConcurrent = (int) ($this->config['maxConcurrent'] ?? 0);
        $maxQueueSize = (int) ($this->config['maxQueueSize'] ?? 10);
        $lockScope = $this->config['lockScope'] ?? 'thread';

        $lockKey = $lockScope === 'channel'
            ? $adapter->getName().':'.$adapter->channelIdFromThreadId($threadId)
            : $threadId;

        $handler = new Handler($this->state, $strategy);

        // HasDynamicSyncPreference: ask adapter at runtime
        if ($adapter instanceof HasDynamicSyncPreference) {
            if ($adapter->requiresSyncResponse()) {
                $this->processDrop($adapter, $threadId, $lockKey, $message, $handler, $processCallback);

                return;
            }

            $this->applyStrategy($strategy, $adapter, $threadId, $lockKey, $message, $handler, $debounceMs, $maxConcurrent, $maxQueueSize, $processCallback);

            return;
        }

        // RequiresSyncResponse: always process inline with lock (drop if contention)
        if ($adapter instanceof RequiresSyncResponse) {
            $this->processDrop($adapter, $threadId, $lockKey, $message, $handler, $processCallback);

            return;
        }

        // Default behavior (RequiresAsyncResponse or no marker):
        // apply the configured strategy exactly as before — strategies handle
        // contention internally (lock + inline when free, queue/debounce when busy)
        $this->applyStrategy($strategy, $adapter, $threadId, $lockKey, $message, $handler, $debounceMs, $maxConcurrent, $maxQueueSize, $processCallback);
    }

    private function applyStrategy(
        Strategy $strategy,
        Adapter $adapter,
        string $threadId,
        string $lockKey,
        Message $message,
        Handler $handler,
        int $debounceMs,
        int $maxConcurrent,
        int $maxQueueSize,
        callable $processCallback,
    ): void {
        match ($strategy) {
            Strategy::Drop => $this->processDrop($adapter, $threadId, $lockKey, $message, $handler, $processCallback),
            Strategy::Queue => $this->processQueue($adapter, $threadId, $lockKey, $message, $handler, $maxQueueSize, $processCallback),
            Strategy::Debounce => $this->processDebounce($adapter, $threadId, $lockKey, $message, $handler, $debounceMs, $maxQueueSize, $processCallback),
            Strategy::Concurrent => $this->processConcurrent($adapter, $threadId, $message, $maxConcurrent, $processCallback),
        };
    }

    private function processDrop(
        Adapter $adapter,
        string $threadId,
        string $lockKey,
        Message $message,
        Handler $handler,
        callable $processCallback,
    ): void {
        $lock = $handler->acquire($lockKey);
        if (! $lock instanceof Lock) {
            return;
        }

        try {
            $processCallback($adapter, $threadId, $message, [], 1);
        } finally {
            $handler->release($lock);
        }
    }

    private function processQueue(
        Adapter $adapter,
        string $threadId,
        string $lockKey,
        Message $message,
        Handler $handler,
        int $maxQueueSize,
        callable $processCallback,
    ): void {
        $entry = new QueueEntry($message->id, serialize($message), microtime(true));
        $handler->enqueue($threadId, $entry, $maxQueueSize);

        $lock = $handler->acquire($lockKey);
        if (! $lock instanceof Lock) {
            return;
        }

        try {
            $this->drainAllQueued($adapter, $threadId, $handler, $processCallback);
        } finally {
            $handler->release($lock);
        }
    }

    private function processDebounce(
        Adapter $adapter,
        string $threadId,
        string $lockKey,
        Message $message,
        Handler $handler,
        int $debounceMs,
        int $maxQueueSize,
        callable $processCallback,
    ): void {
        $lock = $handler->acquire($lockKey);
        if (! $lock instanceof Lock) {
            $handler->enqueue($threadId, new QueueEntry($message->id, serialize($message), microtime(true)), $maxQueueSize);

            return;
        }

        try {
            usleep($debounceMs * 1000);

            $handler->extendLock($lock, 30_000);

            $messages = $this->dequeueAll($threadId, $handler);

            if ($messages !== []) {
                $latest = array_pop($messages);
                $processCallback($adapter, $threadId, $latest, $messages, count($messages) + 1);

                return;
            }

            $processCallback($adapter, $threadId, $message, [], 1);
        } finally {
            $handler->release($lock);
        }
    }

    private function processConcurrent(
        Adapter $adapter,
        string $threadId,
        Message $message,
        int $maxConcurrent,
        callable $processCallback,
    ): void {
        $slotKey = $threadId;

        if ($maxConcurrent > 0) {
            $current = $this->concurrentSlots[$slotKey] ?? 0;
            if ($current >= $maxConcurrent) {
                return;
            }
            $this->concurrentSlots[$slotKey] = $current + 1;
        }

        try {
            $processCallback($adapter, $threadId, $message, [], 1);
        } finally {
            if ($maxConcurrent > 0) {
                $this->concurrentSlots[$slotKey]--;
                if ($this->concurrentSlots[$slotKey] <= 0) {
                    unset($this->concurrentSlots[$slotKey]);
                }
            }
        }
    }

    private function drainAllQueued(
        Adapter $adapter,
        string $threadId,
        Handler $handler,
        callable $processCallback,
    ): void {
        $messages = $this->dequeueAll($threadId, $handler);
        if ($messages === []) {
            return;
        }

        foreach ($messages as $msg) {
            $processCallback($adapter, $threadId, $msg, [], 1);
        }
    }

    /**
     * @return Message[]
     */
    private function dequeueAll(string $threadId, Handler $handler): array
    {
        $messages = [];
        while ($entry = $handler->dequeue($threadId)) {
            $msg = unserialize($entry->payload);
            if ($msg instanceof Message) {
                $msg = new Message(
                    id: $entry->messageId,
                    threadId: $threadId,
                    author: $msg->author,
                    text: $msg->text,
                    formatted: $msg->formatted,
                    attachments: $msg->attachments,
                    isMention: $msg->isMention,
                    isDM: $msg->isDM,
                    raw: $msg->raw,
                );
                $messages[] = $msg;
            }
        }

        return $messages;
    }
}
