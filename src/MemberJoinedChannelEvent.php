<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

use BootDesk\ChatSDK\Core\Contracts\Adapter;

class MemberJoinedChannelEvent
{
    public function __construct(
        public readonly Adapter $adapter,
        public readonly string $channelId,
        public readonly string $userId,
        public readonly ?string $inviterId = null,
        public readonly mixed $raw = null,
    ) {}
}
