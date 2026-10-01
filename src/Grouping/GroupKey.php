<?php

declare(strict_types=1);

namespace DevExtreme\Data\Grouping;

use DateTimeImmutable;
use DateTimeInterface;
use DevExtreme\Data\GroupingInfo;
use InvalidArgumentException;
use Throwable;

final class GroupKey
{
    public const DATE_INTERVALS = ['year', 'quarter', 'month', 'day', 'dayOfWeek', 'hour', 'minute', 'second'];

    public static function compute(mixed $value, GroupingInfo $info): mixed
    {
        $interval = $info->groupInterval;

        if ($interval === null || $interval === '' || $value === null) {
            return $value;
        }

        if (is_numeric($interval)) {
            return self::numericRange($value, $interval);
        }

        $date = self::toDateTime($value);

        return match ($interval) {
            'year' => (int) $date->format('Y'),
            'quarter' => intdiv((int) $date->format('n') + 2, 3),
            'month' => (int) $date->format('n'),
            'day' => (int) $date->format('j'),
            'dayOfWeek' => (int) $date->format('w'),
            'hour' => (int) $date->format('G'),
            'minute' => (int) $date->format('i'),
            'second' => (int) $date->format('s'),
            default => throw new InvalidArgumentException(sprintf('Unsupported group interval "%s".', $interval)),
        };
    }

    /**
     * A stable string identity for a group key (null, scalars and dates are told apart).
     */
    public static function identity(mixed $key): string
    {
        return match (true) {
            $key === null => 'n',
            is_bool($key) => 'b' . (int) $key,
            is_int($key), is_float($key) => 'd' . (string) (float) $key,
            is_string($key) => 's' . $key,
            $key instanceof DateTimeInterface => 't' . $key->format('U.u'),
            is_object($key) => 'o' . spl_object_id($key),
            default => 'x' . serialize($key),
        };
    }

    private static function numericRange(mixed $value, string $interval): int|float
    {
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            throw new InvalidArgumentException('A numeric group interval requires numeric values.');
        }

        $number = self::toNumber($value);
        $step = self::toNumber($interval);

        if ($step == 0) {
            throw new InvalidArgumentException('The group interval must not be zero.');
        }

        if (is_int($number) && is_int($step)) {
            return $number - $number % $step;
        }

        return $number - fmod((float) $number, (float) $step);
    }

    private static function toNumber(int|float|string $value): int|float
    {
        if (is_string($value)) {
            return preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : (float) $value;
        }

        return $value;
    }

    private static function toDateTime(mixed $value): DateTimeInterface
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (new DateTimeImmutable('@' . (int) $value));
        }

        try {
            return new DateTimeImmutable((string) $value);
        } catch (Throwable $e) {
            throw new InvalidArgumentException(sprintf('Cannot convert "%s" to a date for grouping.', (string) $value), 0, $e);
        }
    }
}
