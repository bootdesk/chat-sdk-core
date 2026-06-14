<?php

declare(strict_types=1);

namespace BootDesk\ChatSDK\Core\Support;

class EmojiValue
{
    private static array $registry = [];

    public readonly string $name;

    private function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function get(string $name): self
    {
        if (! isset(self::$registry[$name])) {
            self::$registry[$name] = new self($name);
        }

        return self::$registry[$name];
    }

    public function __toString(): string
    {
        return '{{emoji:'.$this->name.'}}';
    }

    public function toJson(): string
    {
        return $this->__toString();
    }
}
