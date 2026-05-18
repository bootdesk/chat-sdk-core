<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Modals;

class ExternalSelect
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly ?SelectOption $initialOption = null,
        public readonly ?string $placeholder = null,
        public readonly ?int $minQueryLength = null,
        public readonly bool $optional = false,
    ) {}
}
