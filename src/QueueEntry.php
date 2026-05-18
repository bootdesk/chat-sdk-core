<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

class QueueEntry
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $payload,
        public readonly float $enqueuedAt,
    ) {}
}
