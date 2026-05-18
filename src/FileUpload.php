<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

use BootDesk\ChatSDK\Core\Exceptions\ResourceNotFoundException;

class FileUpload
{
    /**
     * @param  string|resource  $data
     */
    public function __construct(
        public mixed $data,
        public readonly string $filename,
        public readonly ?string $mimeType = null,
    ) {}

    public static function fromFilename(string $path): self
    {
        $resource = fopen($path, 'rb');
        if ($resource === false) {
            throw new ResourceNotFoundException('Could not open file path');
        }

        $filename = basename($path);
        $mimeType = mime_content_type($path) ?: null;

        return new self($resource, $filename, $mimeType);
    }

    public function getData(): string
    {
        if (is_resource($this->data)) {
            $contents = stream_get_contents($this->data);
            rewind($this->data);

            return $contents !== false ? $contents : '';
        }

        return (string) $this->data;
    }

    public function getSize(): int
    {
        if (is_resource($this->data)) {
            $stat = fstat($this->data);

            return $stat['size'] ?? 0;
        }

        return strlen((string) $this->data);
    }
}
