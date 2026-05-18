<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Modals;

class TextInput
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly ?string $placeholder = null,
        public readonly ?string $initialValue = null,
        public readonly bool $multiline = false,
        public readonly bool $optional = false,
        public readonly ?int $maxLength = null,
    ) {}
}
