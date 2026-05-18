<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

use BootDesk\ChatSDK\Core\Contracts\Adapter;

class OptionsLoadEvent
{
    public function __construct(
        public readonly Adapter $adapter,
        public readonly string $actionId,
        public readonly string $query,
        public readonly Author $user,
        public readonly mixed $raw = null,
    ) {}
}
