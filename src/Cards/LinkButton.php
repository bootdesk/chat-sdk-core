<?php

namespace BootDesk\ChatSDK\Core\Cards;

class LinkButton implements CardElement
{
    public function __construct(
        public readonly string $label,
        public readonly string $url,
        public readonly ButtonStyle $style = ButtonStyle::Secondary,
    ) {}

    public static function primary(string $label, string $url): self
    {
        return new self($label, $url, ButtonStyle::Primary);
    }

    public static function danger(string $label, string $url): self
    {
        return new self($label, $url, ButtonStyle::Danger);
    }
}
