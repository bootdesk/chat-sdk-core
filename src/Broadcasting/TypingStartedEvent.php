<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Broadcasting;

class TypingStartedEvent extends BroadcastEvent
{
    public function __construct(
        string $threadId,
        public readonly string $userId,
        ?int $timestamp = null,
    ) {
        parent::__construct('typing.started', $threadId, [
            'userId' => $userId,
        ], $timestamp);
    }
}
