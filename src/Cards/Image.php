<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Cards;

class Image implements CardElement
{
    public function __construct(
        public readonly string $url,
        public readonly string $alt = '',
    ) {}
}
