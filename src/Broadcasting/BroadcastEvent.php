<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Broadcasting;

abstract class BroadcastEvent
{
    public readonly int $timestamp;

    public function __construct(
        public readonly string $type,
        public readonly string $threadId,
        public readonly array $data,
        ?int $timestamp = null,
    ) {
        $this->timestamp = $timestamp ?? (int) (microtime(true) * 1000);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'threadId' => $this->threadId,
            'data' => $this->data,
            'timestamp' => $this->timestamp,
        ];
    }
}
