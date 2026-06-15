<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

use BootDesk\ChatSDK\Core\Contracts\Adapter;
use BootDesk\ChatSDK\Core\Contracts\StateAdapter;
use BootDesk\ChatSDK\Core\Contracts\SupportsEditThread;

class Thread
{
    public function __construct(
        public readonly string $id,
        public readonly Chat $chat,
        public readonly Adapter $adapter,
        private readonly StateAdapter $state,
    ) {}

    public function post(string|PostableMessage|Cards\Card $message): SentMessage
    {
        $postable = $this->normalizePostable($message);
        $postable = $this->runSendingMiddleware($postable, 'post');

        if (! $postable instanceof PostableMessage) {
            return new SentMessage(id: '', threadId: $this->id);
        }

        $result = $this->adapter->postMessage($this->id, $postable);

        return $this->runSentMiddleware($postable, $result, 'post');
    }

    public function edit(string $messageId, string|PostableMessage $message): SentMessage
    {
        $postable = $this->normalizePostable($message);
        $postable = $this->runSendingMiddleware($postable, 'edit');

        if (! $postable instanceof PostableMessage) {
            return new SentMessage(id: $messageId, threadId: $this->id);
        }

        $result = $this->adapter->editMessage($this->id, $messageId, $postable);

        return $this->runSentMiddleware($postable, $result, 'edit');
    }

    public function delete(string $messageId): void
    {
        $this->adapter->deleteMessage($this->id, $messageId);
    }

    public function subscribe(): void
    {
        $this->state->subscribe($this->id);
    }

    public function unsubscribe(): void
    {
        $this->state->unsubscribe($this->id);
    }

    public function isSubscribed(): bool
    {
        return $this->state->isSubscribed($this->id);
    }

    public function startTyping(): void
    {
        $this->adapter->startTyping($this->id);
    }

    public function addReaction(string $messageId, string $emoji): void
    {
        $this->adapter->addReaction($this->id, $messageId, $emoji);
    }

    public function removeReaction(string $messageId, string $emoji): void
    {
        $this->adapter->removeReaction($this->id, $messageId, $emoji);
    }

    public function postEphemeral(string $userId, string|PostableMessage $message): void
    {
        $postable = $this->normalizePostable($message);
        $postable = $this->runSendingMiddleware($postable, 'postEphemeral');

        if (! $postable instanceof PostableMessage) {
            return;
        }

        // Ephemeral messages are adapter-specific (e.g., Slack ephemeral).
        // Posting as a regular message if the adapter doesn't support it.
        try {
            $result = $this->adapter->postMessage($this->id, $postable);

            $this->runSentMiddleware($postable, $result, 'postEphemeral');
        } catch (\Throwable) {
            // Silently fail for unsupported operations
        }
    }

    /** @return array<string, mixed> */
    public function getState(): array
    {
        $key = "thread-state:{$this->id}";
        $state = $this->state->get($key);

        return is_array($state) ? $state : [];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function setState(array $state): void
    {
        $key = "thread-state:{$this->id}";
        $this->state->set($key, $state, 30 * 24 * 60 * 60 * 1000);
    }

    public function update(ThreadInfo $threadInfo): ThreadInfo
    {
        if (! $this->adapter instanceof SupportsEditThread) {
            throw new \RuntimeException('Adapter does not support editing threads');
        }

        return $this->adapter->editThread($this->id, $threadInfo);
    }

    public function fetchMessages(?FetchOptions $options = null): FetchResult
    {
        return $this->adapter->fetchMessages(
            threadId: $this->id,
            options: $options
        );
    }

    private function normalizePostable(string|PostableMessage|Cards\Card $message): PostableMessage
    {
        if ($message instanceof PostableMessage) {
            return $message;
        }

        if ($message instanceof Cards\Card) {
            return PostableMessage::card($message);
        }

        return PostableMessage::text($message);
    }

    private function runSendingMiddleware(PostableMessage $message, string $operation): ?PostableMessage
    {
        return $this->chat->getMiddleware()->processSending(
            threadId: $this->id,
            message: $message,
            adapter: $this->adapter,
            operation: $operation,
            handler: fn ($tid, PostableMessage $msg, $adapter, $op): PostableMessage => $msg
        );
    }

    private function runSentMiddleware(PostableMessage $message, SentMessage $result, string $operation): SentMessage
    {
        return $this->chat->getMiddleware()->processSent(
            threadId: $this->id,
            message: $message,
            result: $result,
            adapter: $this->adapter,
            operation: $operation,
            handler: fn ($tid, PostableMessage $msg, SentMessage $res, $adapter, $op): SentMessage => $res
        );
    }
}
