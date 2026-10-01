<?php

declare(strict_types=1);

namespace DevExtreme\Data\Sql;

use InvalidArgumentException;

final class SqliteDialect extends Dialect
{
    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function datePart(string $part, string $expression): string
    {
        $format = match ($part) {
            'year' => '%Y',
            'month' => '%m',
            'day' => '%d',
            'dayOfWeek' => '%w',
            'hour' => '%H',
            'minute' => '%M',
            'second' => '%S',
            'quarter' => null,
            default => throw new InvalidArgumentException(sprintf('Unsupported group interval "%s".', $part)),
        };

        if ($format === null) {
            return sprintf("((CAST(strftime('%%m', %s) AS INTEGER) + 2) / 3)", $expression);
        }

        return sprintf("CAST(strftime('%s', %s) AS INTEGER)", $format, $expression);
    }

    public function truncateToInterval(string $expression, string $interval): string
    {
        $this->assertNumericInterval($interval);

        return sprintf('(CAST((%1$s) * 1.0 / %2$s AS INTEGER) * %2$s)', $expression, $interval);
    }

    public function limitOffset(int $take, int $skip): string
    {
        if ($take < 1 && $skip < 1) {
            return '';
        }

        return sprintf(' LIMIT %d OFFSET %d', $take > 0 ? $take : -1, max(0, $skip));
    }
}
