<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

class Author
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly bool $isMe = false,
        public readonly bool $isBot = false,
    ) {}
}
