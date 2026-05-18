<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

use League\CommonMark\Node\Block\Document;

class Message
{
    public function __construct(
        public readonly string $id,
        public readonly string $threadId,
        public readonly Author $author,
        public readonly string $text,
        public readonly ?Document $formatted = null,
        public readonly array $attachments = [],
        public readonly bool $isMention = false,
        public readonly bool $isDM = false,
        public readonly ?string $raw = null,
    ) {}
}
