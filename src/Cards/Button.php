<?php

namespace BootDesk\ChatSDK\Core\Cards;

class Button implements CardElement
{
    public function __construct(
        public readonly string $label,
        public readonly string $actionId,
        public readonly ButtonStyle $style = ButtonStyle::Secondary,
        public readonly array $data = [],
        public readonly ?string $actionHref = null,
    ) {}

    public static function primary(string $label, string $actionId, array $data = [], ?string $actionHref = null): self
    {
        return new self($label, $actionId, ButtonStyle::Primary, $data, $actionHref);
    }

    public static function danger(string $label, string $actionId, array $data = [], ?string $actionHref = null): self
    {
        return new self($label, $actionId, ButtonStyle::Danger, $data, $actionHref);
    }

    public static function secondary(string $label, string $actionId, array $data = [], ?string $actionHref = null): self
    {
        return new self($label, $actionId, ButtonStyle::Secondary, $data, $actionHref);
    }
}
