<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

class ThreadInfo
{
    public function __construct(
        public readonly string $id,
        public readonly string $channelId,
        public readonly ?string $title = null,
        public readonly ?int $messageCount = null,
        public readonly ?string $topic = null,
        public readonly ?string $iconCustomEmojiId = null,
        public readonly ?bool $isArchived = null,
    ) {}

    public function withParameters(array $overrides): self
    {
        return new self(...[
            'id' => $this->id,
            'channelId' => $this->channelId,
            'title' => $this->title,
            'messageCount' => $this->messageCount,
            'topic' => $this->topic,
            'iconCustomEmojiId' => $this->iconCustomEmojiId,
            'isArchived' => $this->isArchived,
            ...$overrides,
        ]);
    }
}
