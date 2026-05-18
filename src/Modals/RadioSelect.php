<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Modals;

class RadioSelect
{
    /** @param SelectOption[] $options */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly array $options,
        public readonly ?string $initialOption = null,
        public readonly bool $optional = false,
    ) {}
}
