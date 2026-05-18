<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Concurrency;

use BootDesk\ChatSDK\Core\Contracts\StateAdapter;
use BootDesk\ChatSDK\Core\Lock;
use BootDesk\ChatSDK\Core\QueueEntry;

class Handler
{
    private const DEFAULT_LOCK_TTL_MS = 30_000;

    public function __construct(
        private readonly StateAdapter $state,
        private readonly Strategy $strategy = Strategy::Drop,
        private readonly int $lockTtlMs = self::DEFAULT_LOCK_TTL_MS,
    ) {}

    public function acquire(string $threadId): ?Lock
    {
        $lockKey = "process:{$threadId}";

        return match ($this->strategy) {
            Strategy::Drop => $this->state->acquireLock($lockKey, $this->lockTtlMs),
            Strategy::Queue => $this->state->acquireLock($lockKey, $this->lockTtlMs),
            Strategy::Debounce => $this->acquireDebounce($lockKey),
            Strategy::Concurrent => null,
        };
    }

    public function release(?Lock $lock): void
    {
        if ($lock instanceof Lock) {
            $this->state->releaseLock($lock);
        }
    }

    public function enqueue(string $threadId, QueueEntry $entry, int $maxSize = 10): int
    {
        return $this->state->enqueue($threadId, $entry, $maxSize);
    }

    public function dequeue(string $threadId): ?QueueEntry
    {
        return $this->state->dequeue($threadId);
    }

    public function queueDepth(string $threadId): int
    {
        return $this->state->queueDepth($threadId);
    }

    public function extendLock(Lock $lock, int $ttlMs): bool
    {
        return $this->state->extendLock($lock, $ttlMs);
    }

    private function acquireDebounce(string $lockKey): ?Lock
    {
        return $this->state->acquireLock($lockKey, $this->lockTtlMs);
    }
}
