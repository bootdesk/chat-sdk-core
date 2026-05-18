<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Modals;

class SelectOption
{
    public function __construct(
        public readonly string $label,
        public readonly string $value,
        public readonly ?string $description = null,
    ) {}
}
