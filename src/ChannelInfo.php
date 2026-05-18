<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

class ChannelInfo
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name = null,
        public readonly ?string $topic = null,
        public readonly bool $isPrivate = false,
        public readonly ChannelVisibility $visibility = ChannelVisibility::Unknown,
    ) {}
}
