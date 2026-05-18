<?php

namespace BootDesk\ChatSDK\Core\Cards;

class Table implements CardElement
{
    /** @var string[] */
    public readonly array $headers;

    /** @var array<int, string[]> */
    public readonly array $rows;

    /** @var TableAlignment[] */
    public readonly array $align;

    public function __construct(
        array $headers,
        array $rows,
        array $align = [],
    ) {
        $this->headers = array_values($headers);
        $this->rows = array_map(fn ($row): array => array_values($row), $rows);
        $this->align = $align;
    }

    public static function renderAsText(self $table): string
    {
        $widths = array_map('mb_strwidth', $table->headers);

        foreach ($table->rows as $row) {
            foreach ($row as $i => $value) {
                $widths[$i] = max($widths[$i] ?? 0, mb_strwidth($value));
            }
        }

        $padRight = fn (string $val, int $w): string => $val.str_repeat(' ', max(0, $w - mb_strwidth($val)));
        $padLeft = fn (string $val, int $w): string => str_repeat(' ', max(0, $w - mb_strwidth($val))).$val;
        $padCenter = function (string $val, int $w): string {
            $len = mb_strwidth($val);
            if ($len >= $w) {
                return $val;
            }
            $diff = $w - $len;

            return str_repeat(' ', (int) floor($diff / 2)).$val.str_repeat(' ', (int) ceil($diff / 2));
        };

        $applyPad = function (string $val, int $i) use ($table, $widths, $padRight, $padLeft, $padCenter): string {
            $align = $table->align[$i] ?? null;

            return match ($align?->value) {
                'center' => $padCenter($val, $widths[$i]),
                'right' => $padLeft($val, $widths[$i]),
                default => $padRight($val, $widths[$i]),
            };
        };

        $lines = [];

        $lines[] = '| '.implode(' | ', array_map(
            fn (string $h, int $i): string => $applyPad($h, $i),
            $table->headers,
            array_keys($table->headers),
        )).' |';

        $separators = [];
        foreach (array_keys($table->headers) as $i) {
            $w = max(3, $widths[$i]);
            $align = $table->align[$i] ?? null;
            $separators[] = match ($align?->value) {
                'center' => ':'.str_repeat('-', $w - 2).':',
                'right' => str_repeat('-', $w - 1).':',
                'left' => ':'.str_repeat('-', $w - 1),
                default => str_repeat('-', $w),
            };
        }
        $lines[] = '| '.implode(' | ', $separators).' |';

        foreach ($table->rows as $row) {
            $lines[] = '| '.implode(' | ', array_map(
                fn (string $val, int $i): string => $applyPad($val, $i),
                $row,
                array_keys($row),
            )).' |';
        }

        return implode("\n", $lines);
    }
}
