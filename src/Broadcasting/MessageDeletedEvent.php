<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Broadcasting;

class MessageDeletedEvent extends BroadcastEvent
{
    public function __construct(
        string $threadId,
        public readonly string $messageId,
        ?int $timestamp = null,
    ) {
        parent::__construct('message.deleted', $threadId, [
            'messageId' => $messageId,
        ], $timestamp);
    }
}
