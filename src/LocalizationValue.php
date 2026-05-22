<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

class LocalizationValue
{
    public function __construct(
        public readonly LocalizationType $type,
        public readonly string $value,
    ) {}
}
