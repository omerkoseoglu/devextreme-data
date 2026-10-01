<?php

declare(strict_types=1);

namespace DevExtreme\Data\Support;

use DateTimeInterface;
use Stringable;

final class Str
{
    /**
     * Converts a value to a string the way filters expect: null is empty, booleans are "true"/"false".
     */
    public static function from(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'true' : 'false',
            is_string($value) => $value,
            is_int($value), is_float($value) => (string) $value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d\TH:i:s'),
            $value instanceof Stringable => (string) $value,
            default => '',
        };
    }

    public static function lower(string $value): string
    {
        return mb_strtolower($value);
    }
}
