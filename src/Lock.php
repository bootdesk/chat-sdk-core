<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

class Lock
{
    public function __construct(
        public readonly string $key,
        public readonly string $token,
        public readonly int $ttlMs,
    ) {}
}
