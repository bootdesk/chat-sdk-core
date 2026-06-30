<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

use Nyholm\Psr7\Stream;
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
        public readonly ?float $lat = null,
        public readonly ?float $lng = null,
        public readonly ?string $address = null,
    ) {
        if ($fetchData !== null && ! is_callable($fetchData)) {
            throw new \InvalidArgumentException('fetchData must be a callable or null');
        }

        $this->fetchData = $fetchData;
    }

    public function isDataUrl(): bool
    {
        return $this->url !== null && str_starts_with($this->url, 'data:');
    }

    public function read(): ?StreamInterface
    {
        if ($this->isDataUrl()) {
            $comma = strpos($this->url, ',');
            if ($comma === false) {
                return null;
            }
            $encoded = substr($this->url, $comma + 1);
            $decoded = base64_decode($encoded, true);
            if ($decoded === false) {
                return null;
            }
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $decoded);
            rewind($stream);

            return new Stream($stream);
        }

        if ($this->fetchData === null) {
            return null;
        }

        return ($this->fetchData)($this);
    }

    /** @param callable(Attachment): StreamInterface $fetchData */
    public function withFetchOptions(callable $fetchData, ?array $fetchMetadata = null): self
    {
        return new self(
            type: $this->type,
            url: $this->url,
            name: $this->name,
            mimeType: $this->mimeType,
            size: $this->size,
            width: $this->width,
            height: $this->height,
            fetchData: $fetchData,
            fetchMetadata: $fetchMetadata ?? $this->fetchMetadata,
            lat: $this->lat,
            lng: $this->lng,
            address: $this->address,
        );
    }

    public static function location(
        float $lat,
        float $lng,
        ?string $name = null,
        ?string $address = null,
    ): self {
        $geojson = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$lng, $lat],
            ],
        ];

        if ($name !== null || $address !== null) {
            $geojson['properties'] = array_filter([
                'name' => $name,
                'address' => $address,
            ]);
        }

        return new self(
            type: 'location',
            url: 'data:application/geo+json;base64,'.base64_encode(json_encode($geojson)),
            name: $name,
            mimeType: 'application/geo+json',
            lat: $lat,
            lng: $lng,
            address: $address,
        );
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
            'lat' => $this->lat,
            'lng' => $this->lng,
            'address' => $this->address,
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
        $this->lat = $data['lat'] ?? null;
        $this->lng = $data['lng'] ?? null;
        $this->address = $data['address'] ?? null;
        $this->fetchData = null;
    }

    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
            'url' => $this->url,
            'name' => $this->name,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
        ];

        if ($this->lat !== null || $this->lng !== null) {
            $result['lat'] = $this->lat;
            $result['lng'] = $this->lng;
        }

        if ($this->address !== null) {
            $result['address'] = $this->address;
        }

        return $result;
    }
}
