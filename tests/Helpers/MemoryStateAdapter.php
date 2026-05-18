<?php

namespace BootDesk\ChatSDK\Core\Tests\Helpers;

use BootDesk\ChatSDK\Core\Contracts\StateAdapter;
use BootDesk\ChatSDK\Core\Lock;
use BootDesk\ChatSDK\Core\QueueEntry;

class MemoryStateAdapter implements StateAdapter
{
    private array $store = [];

    private array $locks = [];

    private array $queues = [];

    private array $subscriptions = [];

    private array $lists = [];

    private bool $connected = false;

    public function connect(): void
    {
        $this->connected = true;
    }

    public function disconnect(): void
    {
        $this->connected = false;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function subscribe(string $threadId): void
    {
        $this->subscriptions[$threadId] = true;
    }

    public function unsubscribe(string $threadId): void
    {
        unset($this->subscriptions[$threadId]);
    }

    public function isSubscribed(string $threadId): bool
    {
        return isset($this->subscriptions[$threadId]);
    }

    public function acquireLock(string $lockKey, int $ttlMs): ?Lock
    {
        $now = microtime(true);
        if (isset($this->locks[$lockKey]) && $this->locks[$lockKey]['expiresAt'] > $now) {
            return null;
        }

        $token = bin2hex(random_bytes(16));
        $this->locks[$lockKey] = [
            'token' => $token,
            'expiresAt' => $now + ($ttlMs / 1000),
        ];

        return new Lock($lockKey, $token, $ttlMs);
    }

    public function extendLock(Lock $lock, int $ttlMs): bool
    {
        if (! isset($this->locks[$lock->key]) || $this->locks[$lock->key]['token'] !== $lock->token) {
            return false;
        }
        $this->locks[$lock->key]['expiresAt'] = microtime(true) + ($ttlMs / 1000);

        return true;
    }

    public function releaseLock(Lock $lock): void
    {
        if (isset($this->locks[$lock->key]) && $this->locks[$lock->key]['token'] === $lock->token) {
            unset($this->locks[$lock->key]);
        }
    }

    public function forceReleaseLock(string $lockKey): void
    {
        unset($this->locks[$lockKey]);
    }

    public function get(string $key): mixed
    {
        if (! isset($this->store[$key])) {
            return null;
        }

        if (isset($this->store[$key]['expiresAt']) && $this->store[$key]['expiresAt'] < microtime(true)) {
            unset($this->store[$key]);

            return null;
        }

        return $this->store[$key]['value'];
    }

    public function set(string $key, mixed $value, ?int $ttlMs = null): void
    {
        $this->store[$key] = [
            'value' => $value,
            'expiresAt' => $ttlMs !== null ? microtime(true) + ($ttlMs / 1000) : null,
        ];
    }

    public function setIfNotExists(string $key, mixed $value, ?int $ttlMs = null): bool
    {
        if ($this->get($key) !== null) {
            return false;
        }
        $this->set($key, $value, $ttlMs);

        return true;
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
        unset($this->lists[$key]);
    }

    public function appendToList(string $key, mixed $value, array $options = []): void
    {
        if (! isset($this->lists[$key])) {
            $this->lists[$key] = [];
        }
        $this->lists[$key][] = $value;

        $maxLength = $options['maxLength'] ?? null;
        if ($maxLength !== null && count($this->lists[$key]) > $maxLength) {
            $this->lists[$key] = array_slice($this->lists[$key], -$maxLength);
        }
    }

    public function getList(string $key): array
    {
        return $this->lists[$key] ?? [];
    }

    public function enqueue(string $threadId, QueueEntry $entry, int $maxSize): int
    {
        if (! isset($this->queues[$threadId])) {
            $this->queues[$threadId] = [];
        }
        $this->queues[$threadId][] = $entry;

        if (count($this->queues[$threadId]) > $maxSize) {
            array_shift($this->queues[$threadId]);
        }

        return count($this->queues[$threadId]);
    }

    public function dequeue(string $threadId): ?QueueEntry
    {
        if (empty($this->queues[$threadId])) {
            return null;
        }

        return array_shift($this->queues[$threadId]);
    }

    public function queueDepth(string $threadId): int
    {
        return count($this->queues[$threadId] ?? []);
    }

    public function storeModalContext(string $adapterName, string $contextId, array $data, int $ttlMs): void
    {
        $this->set("modal-context:{$adapterName}:{$contextId}", $data, $ttlMs);
    }

    public function getAndDeleteModalContext(string $adapterName, string $contextId): ?array
    {
        $key = "modal-context:{$adapterName}:{$contextId}";
        $data = $this->get($key);
        if ($data !== null) {
            $this->delete($key);
        }

        return is_array($data) ? $data : null;
    }
}
