<?php

namespace BootDesk\ChatSDK\Core\Support;

class AdapterRegistry
{
    private static array $adapters = [];

    public static function register(string $name, string $adapterClass): void
    {
        self::$adapters[$name] = $adapterClass;
    }

    public static function all(): array
    {
        return self::$adapters;
    }

    public static function get(string $name): ?string
    {
        return self::$adapters[$name] ?? null;
    }

    public static function clear(): void
    {
        self::$adapters = [];
    }
}
