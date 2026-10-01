<?php

declare(strict_types=1);

namespace DevExtreme\Data\Sql;

use InvalidArgumentException;
use PDO;

abstract class Dialect
{
    public static function fromPdo(PDO $pdo): self
    {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        return match ($driver) {
            'sqlite' => new SqliteDialect(),
            'mysql' => new MySqlDialect(),
            'pgsql' => new PostgresDialect(),
            default => throw new InvalidArgumentException(sprintf(
                'PDO driver "%s" has no built-in dialect; pass a custom Dialect to PdoSource.',
                $driver,
            )),
        };
    }

    abstract public function quoteIdentifier(string $identifier): string;

    /**
     * Expression for one date/time component ("year", "quarter", "month", "day", "dayOfWeek", "hour", "minute", "second").
     * dayOfWeek is 0 (Sunday) - 6.
     */
    abstract public function datePart(string $part, string $expression): string;

    /**
     * Expression rounding a number down (towards zero) to a multiple of $interval.
     */
    abstract public function truncateToInterval(string $expression, string $interval): string;

    /**
     * LIMIT/OFFSET clause (with leading space), or an empty string when neither applies.
     */
    abstract public function limitOffset(int $take, int $skip): string;

    public function castToText(string $expression): string
    {
        return sprintf('CAST(%s AS TEXT)', $expression);
    }

    public function lower(string $expression): string
    {
        return sprintf('LOWER(%s)', $expression);
    }

    /**
     * ORDER BY direction keeping NULL the smallest value (like the in-memory source does).
     */
    public function orderDirection(bool $desc): string
    {
        return $desc ? 'DESC' : 'ASC';
    }

    /**
     * Pattern matching operator. It must be case-insensitive like the in-memory source,
     * unless the database collation says otherwise (MySQL).
     */
    public function likeOperator(): string
    {
        return 'LIKE';
    }

    public function likeEscape(): string
    {
        return '!';
    }

    protected function assertNumericInterval(string $interval): void
    {
        if (preg_match('/^\d+(\.\d+)?$/', $interval) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid group interval "%s".', $interval));
        }
    }
}
