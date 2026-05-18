<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\Lock;
use BootDesk\ChatSDK\Core\QueueEntry;

interface StateAdapter
{
    public function connect(): void;

    public function disconnect(): void;

    public function subscribe(string $threadId): void;

    public function unsubscribe(string $threadId): void;

    public function isSubscribed(string $threadId): bool;

    public function acquireLock(string $lockKey, int $ttlMs): ?Lock;

    public function extendLock(Lock $lock, int $ttlMs): bool;

    public function releaseLock(Lock $lock): void;

    public function forceReleaseLock(string $lockKey): void;

    public function get(string $key): mixed;

    public function set(string $key, mixed $value, ?int $ttlMs = null): void;

    public function setIfNotExists(string $key, mixed $value, ?int $ttlMs = null): bool;

    public function delete(string $key): void;

    public function appendToList(string $key, mixed $value, array $options = []): void;

    public function getList(string $key): array;

    public function enqueue(string $threadId, QueueEntry $entry, int $maxSize): int;

    public function dequeue(string $threadId): ?QueueEntry;

    public function queueDepth(string $threadId): int;

    public function storeModalContext(string $adapterName, string $contextId, array $data, int $ttlMs): void;

    public function getAndDeleteModalContext(string $adapterName, string $contextId): ?array;
}
