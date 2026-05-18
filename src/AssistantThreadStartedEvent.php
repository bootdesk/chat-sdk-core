<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

use BootDesk\ChatSDK\Core\Contracts\Adapter;

class AssistantThreadStartedEvent
{
    public function __construct(
        public readonly Adapter $adapter,
        public readonly string $channelId,
        public readonly string $threadId,
        public readonly ?string $threadTs,
        public readonly string $userId,
        public readonly mixed $context,
        public readonly mixed $raw = null,
    ) {}
}
