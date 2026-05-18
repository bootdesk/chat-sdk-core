<?php

namespace BootDesk\ChatSDK\Core;

use BootDesk\ChatSDK\Core\Cards\Card;

class PostableMessage
{
    /** @param Attachment[] $attachments */
    /** @param FileUpload[] $files */
    public function __construct(
        public readonly string|Card|Template $content,
        public readonly ?string $replyToMessageId = null,
        public readonly array $attachments = [],
        public readonly array $files = [],
        public readonly ?array $metadata = null,
    ) {}

    public static function text(string $text): self
    {
        return new self(content: $text);
    }

    public static function markdown(string $markdown): self
    {
        return new self(content: $markdown);
    }

    public static function card(Card $card): self
    {
        return new self(content: $card);
    }

    public static function template(Template $template): self
    {
        return new self(content: $template);
    }

    public function isCard(): bool
    {
        return $this->content instanceof Card;
    }

    public function isTemplate(): bool
    {
        return $this->content instanceof Template;
    }

    public function getTextContent(): string
    {
        if ($this->content instanceof Card) {
            return $this->content->getFallbackText();
        }

        if ($this->content instanceof Template) {
            return (string) $this->content;
        }

        return $this->content;
    }
}
