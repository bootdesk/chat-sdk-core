<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

class Attachment
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $url = null,
        public readonly ?string $name = null,
        public readonly ?string $mimeType = null,
        public readonly ?int $size = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public $fetchData = null,
        public readonly ?array $fetchMetadata = null,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'url' => $this->url,
            'name' => $this->name,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
        ];
    }
}
