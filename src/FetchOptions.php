<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

class FetchOptions
{
    public function __construct(
        public readonly ?string $before = null,
        public readonly ?string $after = null,
        public readonly int $limit = 50,
    ) {}
}
