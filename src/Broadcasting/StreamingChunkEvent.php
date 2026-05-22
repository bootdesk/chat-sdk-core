<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Broadcasting;

class StreamingChunkEvent extends BroadcastEvent
{
    public function __construct(
        string $threadId,
        public readonly string $messageId,
        public readonly string $chunk,
        public readonly bool $isFinal = false,
        ?int $timestamp = null,
    ) {
        parent::__construct('streaming.chunk', $threadId, [
            'messageId' => $messageId,
            'chunk' => $chunk,
            'isFinal' => $isFinal,
        ], $timestamp);
    }
}
