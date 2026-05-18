<?php

namespace BootDesk\ChatSDK\Core\Cards;

class Section implements CardElement
{
    private ?string $text = null;

    /** @var array<string, string> */
    private array $fields = [];

    public function text(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function fields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function getFields(): array
    {
        return $this->fields;
    }
}
