<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Cards;

class Link implements CardElement
{
    public function __construct(
        public readonly string $label,
        public readonly string $url,
    ) {}
}
