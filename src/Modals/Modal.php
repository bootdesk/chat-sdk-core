<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Modals;

class Modal
{
    /** @param array<TextInput|Select|ExternalSelect|RadioSelect> $children */
    public function __construct(
        public readonly string $callbackId,
        public readonly string $title,
        public readonly array $children = [],
        public readonly ?string $submitLabel = null,
        public readonly ?string $closeLabel = null,
        public readonly ?string $privateMetadata = null,
        public readonly ?string $callbackUrl = null,
        public readonly ?bool $notifyOnClose = null,
    ) {}
}
