<?php

namespace BootDesk\ChatSDK\Core\Cards;

class Button implements CardElement
{
    public function __construct(
        public readonly string $label,
        public readonly string $actionId,
        public readonly ButtonStyle $style = ButtonStyle::Secondary,
        public readonly array $data = [],
    ) {}

    public static function primary(string $label, string $actionId, array $data = []): self
    {
        return new self($label, $actionId, ButtonStyle::Primary, $data);
    }

    public static function danger(string $label, string $actionId, array $data = []): self
    {
        return new self($label, $actionId, ButtonStyle::Danger, $data);
    }

    public static function secondary(string $label, string $actionId, array $data = []): self
    {
        return new self($label, $actionId, ButtonStyle::Secondary, $data);
    }
}
