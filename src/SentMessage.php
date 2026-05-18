<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

class SentMessage
{
    public function __construct(
        public readonly string $id,
        public readonly string $threadId,
        public readonly ?string $timestamp = null,
    ) {}
}
