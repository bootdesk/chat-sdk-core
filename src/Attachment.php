<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

use Psr\Http\Message\StreamInterface;

class Attachment
{
    /** @var (callable(Attachment): StreamInterface)|null */
    public $fetchData;

    public function __construct(
        public readonly string $type,
        public readonly ?string $url = null,
        public readonly ?string $name = null,
        public readonly ?string $mimeType = null,
        public readonly ?int $size = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        mixed $fetchData = null,
        public readonly ?array $fetchMetadata = null,
    ) {
        if ($fetchData !== null && ! is_callable($fetchData)) {
            throw new \InvalidArgumentException('fetchData must be a callable or null');
        }

        $this->fetchData = $fetchData;
    }

    public function read(): ?StreamInterface
    {
        if ($this->fetchData === null) {
            return null;
        }

        return ($this->fetchData)($this);
    }

    public function __serialize(): array
    {
        return [
            'type' => $this->type,
            'url' => $this->url,
            'name' => $this->name,
            'mimeType' => $this->mimeType,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'fetchMetadata' => $this->fetchMetadata,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->type = $data['type'];
        $this->url = $data['url'];
        $this->name = $data['name'];
        $this->mimeType = $data['mimeType'];
        $this->size = $data['size'];
        $this->width = $data['width'];
        $this->height = $data['height'];
        $this->fetchMetadata = $data['fetchMetadata'];
        $this->fetchData = null;
    }

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
