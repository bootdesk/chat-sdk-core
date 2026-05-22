<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Broadcasting;

class ReactionRemovedEvent extends BroadcastEvent
{
    public function __construct(
        string $threadId,
        public readonly string $messageId,
        public readonly string $emoji,
        public readonly array $user,
        ?int $timestamp = null,
    ) {
        parent::__construct('reaction.removed', $threadId, [
            'messageId' => $messageId,
            'emoji' => $emoji,
            'user' => $user,
        ], $timestamp);
    }
}
