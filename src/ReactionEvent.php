<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

class ReactionEvent
{
    public function __construct(
        public readonly string $emoji,
        public readonly string $messageId,
        public readonly Thread $thread,
        public readonly Author $user,
        public readonly bool $added = true,
        public readonly string $rawEmoji = '',
        public readonly mixed $raw = null,
    ) {}
}
