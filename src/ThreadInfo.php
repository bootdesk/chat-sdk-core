<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

class ThreadInfo
{
    public function __construct(
        public readonly string $id,
        public readonly string $channelId,
        public readonly ?string $title = null,
        public readonly ?int $messageCount = null,
    ) {}
}
