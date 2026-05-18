<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

class UserInfo
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name = null,
        public readonly ?string $email = null,
    ) {}
}
