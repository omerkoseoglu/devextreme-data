<?php

declare(strict_types=1);

namespace DevExtreme\Data\Support;

use DateTimeImmutable;
use DateTimeInterface;
use Stringable;
use Throwable;

final class Compare
{
    private const DATE_LIKE = '/^(\d{4}-\d{2}-\d{2})(?:[T ](\d{2}:\d{2})(?::(\d{2}))?(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})?$/';

    /**
     * Total ordering used for sorting and min/max. `null` is smaller than everything.
     */
    public static function compare(mixed $a, mixed $b): int
    {
        if ($a === null) {
            return $b === null ? 0 : -1;
        }

        if ($b === null) {
            return 1;
        }

        if ($a instanceof DateTimeInterface || $b instanceof DateTimeInterface) {
            $ta = self::toTimestamp($a);
            $tb = self::toTimestamp($b);
            if ($ta !== null && $tb !== null) {
                return $ta <=> $tb;
            }
        }

        if (is_string($a) && is_string($b)) {
            if (is_numeric($a) && is_numeric($b)) {
                return $a + 0 <=> $b + 0;
            }

            return self::compareStringsForSort($a, $b, true);
        }

        if (is_bool($a) || is_bool($b)) {
            return (int) (bool) $a <=> (int) (bool) $b;
        }

        if (is_scalar($a) && is_scalar($b)) {
            return $a <=> $b;
        }

        return Str::from($a) <=> Str::from($b);
    }

    public static function compareStrings(string $a, string $b, bool $ignoreCase): int
    {
        if ($ignoreCase) {
            return strcmp(mb_strtolower($a), mb_strtolower($b)) <=> 0;
        }

        return strcmp($a, $b) <=> 0;
    }

    /**
     * Sort order for strings: case-insensitive, with a case-sensitive tie-break so the order is total.
     */
    public static function compareStringsForSort(string $a, string $b, bool $ignoreCase): int
    {
        return self::compareStrings($a, $b, $ignoreCase) ?: (strcmp($a, $b) <=> 0);
    }

    /**
     * Compares a data value with a value coming from a client filter.
     *
     * Both values are expected to be non-null. Returns `null` when the client value
     * cannot be converted to the data value's type (the filter condition is then false).
     */
    public static function forFilter(mixed $item, mixed $client, bool $ignoreCase): ?int
    {
        if ($item instanceof DateTimeInterface) {
            $b = self::toTimestamp($client);

            return $b === null ? null : (self::toTimestamp($item) <=> $b);
        }

        if (is_int($item) || is_float($item)) {
            if (is_bool($client)) {
                $client = (int) $client;
            }

            if (is_int($client) || is_float($client) || (is_string($client) && is_numeric($client))) {
                return $item <=> $client + 0;
            }

            return null;
        }

        if (is_bool($item)) {
            $b = self::toBool($client);

            return $b === null ? null : ((int) $item <=> (int) $b);
        }

        if (is_string($item) || $item instanceof Stringable) {
            $item = (string) $item;

            if (is_array($client) || is_object($client) && !$client instanceof Stringable && !$client instanceof DateTimeInterface) {
                return null;
            }

            if ((is_int($client) || is_float($client)) && is_numeric($item)) {
                return $item + 0 <=> $client;
            }

            if (is_string($client)) {
                $a = self::normalizeDateString($item);
                $b = $a === null ? null : self::normalizeDateString($client);
                if ($a !== null && $b !== null) {
                    return strcmp($a, $b) <=> 0;
                }
            }

            return self::compareStrings($item, Str::from($client), $ignoreCase);
        }

        return null;
    }

    /**
     * Rewrites date-like strings ("2024-05-01", "2024-05-01T10:00:00.000Z", "2024-05-01 10:00") to the
     * wall-clock form "Y-m-d H:i:s" (timezone and fraction dropped) so that they compare chronologically
     * as plain strings. Returns null for anything else.
     */
    public static function normalizeDateString(string $value): ?string
    {
        if (preg_match(self::DATE_LIKE, $value, $m) !== 1) {
            return null;
        }

        return $m[1] . ' ' . ($m[2] ?? '00:00') . ':' . ($m[3] ?? '00');
    }

    public static function toTimestamp(mixed $value): ?float
    {
        if ($value instanceof DateTimeInterface) {
            return (float) $value->format('U.u');
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && $value !== '') {
            try {
                return (float) (new DateTimeImmutable($value))->format('U.u');
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    public static function toBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return match (strtolower($value)) {
                'true', '1' => true,
                'false', '0' => false,
                default => null,
            };
        }

        return null;
    }
}
