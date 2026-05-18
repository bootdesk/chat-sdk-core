<?php

namespace BootDesk\ChatSDK\Core\Support;

class Str
{
    public static function startsWith(string $haystack, string $needle): bool
    {
        return str_starts_with($haystack, $needle);
    }

    public static function camel(string $value): string
    {
        $words = explode('_', $value);
        $first = array_shift($words);

        return $first.implode('', array_map('ucfirst', $words));
    }

    public static function snake(string $value): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
    }
}
