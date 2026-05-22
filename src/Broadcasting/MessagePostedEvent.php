<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Broadcasting;

use BootDesk\ChatSDK\Core\Attachment;
use BootDesk\ChatSDK\Core\Cards\Card;

class MessagePostedEvent extends BroadcastEvent
{
    /** @param Attachment[] $attachments */
    public function __construct(
        string $threadId,
        public readonly string $messageId,
        public readonly string $text,
        public readonly array $author,
        public readonly ?Card $card = null,
        public readonly array $attachments = [],
        ?int $timestamp = null,
    ) {
        $data = [
            'messageId' => $messageId,
            'text' => $text,
            'author' => $author,
        ];

        if ($card instanceof Card) {
            $data['card'] = $card->toArray();
        }

        if ($attachments !== []) {
            $data['attachments'] = array_map(fn (Attachment $a): array => [
                'type' => $a->type,
                'url' => $a->url,
                'name' => $a->name,
                'mimeType' => $a->mimeType,
                'size' => $a->size,
            ], $attachments);
        }

        parent::__construct('message.posted', $threadId, $data, $timestamp);
    }
}
