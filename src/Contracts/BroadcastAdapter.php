<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Contracts;

use BootDesk\ChatSDK\Core\Broadcasting\BroadcastEvent;

interface BroadcastAdapter
{
    public function connect(): void;

    public function disconnect(): void;

    public function broadcast(string $threadId, BroadcastEvent $event, array $options = []): void;

    public function broadcastToUser(string $threadId, string|array $userIds, BroadcastEvent $event, array $options = []): void;

    public function isBroadcastingAvailable(string $threadId): bool;
}
