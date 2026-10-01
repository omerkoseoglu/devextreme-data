<?php

declare(strict_types=1);

namespace DevExtreme\Data\Sql;

use InvalidArgumentException;

final class MySqlDialect extends Dialect
{
    public function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public function castToText(string $expression): string
    {
        return sprintf('CAST(%s AS CHAR)', $expression);
    }

    public function datePart(string $part, string $expression): string
    {
        return match ($part) {
            'year' => sprintf('YEAR(%s)', $expression),
            'quarter' => sprintf('QUARTER(%s)', $expression),
            'month' => sprintf('MONTH(%s)', $expression),
            'day' => sprintf('DAYOFMONTH(%s)', $expression),
            'dayOfWeek' => sprintf('(DAYOFWEEK(%s) - 1)', $expression),
            'hour' => sprintf('HOUR(%s)', $expression),
            'minute' => sprintf('MINUTE(%s)', $expression),
            'second' => sprintf('SECOND(%s)', $expression),
            default => throw new InvalidArgumentException(sprintf('Unsupported group interval "%s".', $part)),
        };
    }

    public function truncateToInterval(string $expression, string $interval): string
    {
        $this->assertNumericInterval($interval);

        return sprintf('(TRUNCATE((%1$s) / %2$s, 0) * %2$s)', $expression, $interval);
    }

    public function limitOffset(int $take, int $skip): string
    {
        if ($take < 1 && $skip < 1) {
            return '';
        }

        return sprintf(' LIMIT %s OFFSET %d', $take > 0 ? (string) $take : '18446744073709551615', max(0, $skip));
    }
}
