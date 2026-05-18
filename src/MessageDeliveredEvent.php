<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

class MessageDeliveredEvent
{
    /** @param string[] $messageIds */
    public function __construct(
        public readonly array $messageIds,
        public readonly string $threadId,
        public readonly string $userId,
        public readonly mixed $raw = null,
    ) {}
}
