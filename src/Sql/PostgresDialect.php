<?php

declare(strict_types=1);

namespace DevExtreme\Data\Sql;

use InvalidArgumentException;

final class PostgresDialect extends Dialect
{
    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function datePart(string $part, string $expression): string
    {
        $field = match ($part) {
            'year' => 'YEAR',
            'quarter' => 'QUARTER',
            'month' => 'MONTH',
            'day' => 'DAY',
            'dayOfWeek' => 'DOW',
            'hour' => 'HOUR',
            'minute' => 'MINUTE',
            'second' => 'SECOND',
            default => throw new InvalidArgumentException(sprintf('Unsupported group interval "%s".', $part)),
        };

        return sprintf('CAST(EXTRACT(%s FROM %s) AS INTEGER)', $field, $expression);
    }

    public function truncateToInterval(string $expression, string $interval): string
    {
        $this->assertNumericInterval($interval);

        return sprintf('(TRUNC(CAST(%1$s AS NUMERIC) / %2$s) * %2$s)', $expression, $interval);
    }

    public function limitOffset(int $take, int $skip): string
    {
        if ($take < 1 && $skip < 1) {
            return '';
        }

        return sprintf(' LIMIT %s OFFSET %d', $take > 0 ? (string) $take : 'ALL', max(0, $skip));
    }

    public function likeOperator(): string
    {
        return 'ILIKE'; // LIKE is case-sensitive in PostgreSQL
    }

    public function orderDirection(bool $desc): string
    {
        // PostgreSQL treats NULL as the largest value by default; keep it the smallest.
        return $desc ? 'DESC NULLS LAST' : 'ASC NULLS FIRST';
    }
}
