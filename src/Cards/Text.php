<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Cards;

class Text implements CardElement
{
    public function __construct(
        public readonly string $content,
        public readonly TextStyle $style = TextStyle::Plain,
    ) {}
}
