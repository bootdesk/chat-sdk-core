<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Broadcasting;

use BootDesk\ChatSDK\Core\Cards\Card;

class MessageEditedEvent extends BroadcastEvent
{
    public function __construct(
        string $threadId,
        public readonly string $messageId,
        public readonly string $newText,
        public readonly ?Card $card = null,
        ?int $timestamp = null,
    ) {
        $data = [
            'messageId' => $messageId,
            'newText' => $newText,
        ];

        if ($card instanceof Card) {
            $data['card'] = $card->toArray();
        }

        parent::__construct('message.edited', $threadId, $data, $timestamp);
    }
}
