<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

class MessageReadEvent
{
    public function __construct(
        public readonly string $threadId,
        public readonly string $userId,
        public readonly mixed $raw = null,
        public readonly ?int $timestamp = null,
        public readonly ?string $originId = null,
    ) {}
}
