<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core;

abstract class Template
{
    public function __construct(
        private readonly string $name,
        private readonly string $language,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    abstract public function __toString(): string;

    abstract public function toArray(): array;
}
