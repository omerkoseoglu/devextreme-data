<?php

declare(strict_types=1);

namespace DevExtreme\Data\Tests\Unit;

use DevExtreme\Data\Filter\FilterParser;
use DevExtreme\Data\Sql\ColumnResolver;
use DevExtreme\Data\Sql\Dialect;
use DevExtreme\Data\Sql\MySqlDialect;
use DevExtreme\Data\Sql\PostgresDialect;
use DevExtreme\Data\Sql\SqlFilterCompiler;
use DevExtreme\Data\Sql\SqliteDialect;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Checks the SQL text for every dialect without needing a database server.
 */
final class SqlGenerationTest extends TestCase
{
    /**
     * @param list<mixed> $filter
     *
     * @return array{0: string, 1: list<mixed>}
     */
    private function compile(Dialect $dialect, array $filter, bool $lower = false): array
    {
        $fragment = (new SqlFilterCompiler($dialect, new ColumnResolver($dialect), $lower))->compile(FilterParser::parse($filter));

        return [$fragment->sql, $fragment->params];
    }

    public function testQuoting(): void
    {
        self::assertSame('"a""b"', (new SqliteDialect())->quoteIdentifier('a"b'));
        self::assertSame('"a""b"', (new PostgresDialect())->quoteIdentifier('a"b'));
        self::assertSame('`a``b`', (new MySqlDialect())->quoteIdentifier('a`b'));
    }

    public function testComparisonUsesTwoValuedLogic(): void
    {
        self::assertSame(['("price" IS NOT NULL AND "price" > ?)', [10]], $this->compile(new SqliteDialect(), ['price', '>', 10]));
        self::assertSame(['(`price` IS NULL OR `price` <> ?)', [10]], $this->compile(new MySqlDialect(), ['price', '<>', 10]));
    }

    public function testNullComparisons(): void
    {
        self::assertSame(['"a" IS NULL', []], $this->compile(new SqliteDialect(), ['a', '=', null]));
        self::assertSame(['"a" IS NOT NULL', []], $this->compile(new SqliteDialect(), ['a', '<>', null]));
        self::assertSame(['1 = 0', []], $this->compile(new SqliteDialect(), ['a', '>', null]));
    }

    public function testLikeEscapingAndDialectCasts(): void
    {
        [$sql, $params] = $this->compile(new MySqlDialect(), ['name', 'contains', '50%_!']);
        self::assertSame("COALESCE(CAST(`name` AS CHAR), '') LIKE ? ESCAPE '!'", $sql);
        self::assertSame(['%50!%!_!!%'], $params);

        [$sql] = $this->compile(new PostgresDialect(), ['name', 'notcontains', 'x']);
        self::assertSame("NOT (COALESCE(CAST(\"name\" AS TEXT), '') ILIKE ? ESCAPE '!')", $sql);

        [, $params] = $this->compile(new SqliteDialect(), ['name', 'startswith', 'ab']);
        self::assertSame(['ab%'], $params);
        [, $params] = $this->compile(new SqliteDialect(), ['name', 'endswith', 'ab']);
        self::assertSame(['%ab'], $params);
    }

    public function testStringToLower(): void
    {
        self::assertSame(['("n" IS NOT NULL AND LOWER("n") = ?)', ['abc']], $this->compile(new SqliteDialect(), ['n', 'ABC'], true));
        self::assertSame(["LOWER(COALESCE(CAST(\"n\" AS TEXT), '')) LIKE ? ESCAPE '!'", ['%abc%']], $this->compile(new SqliteDialect(), ['n', 'contains', 'ABC'], true));
    }

    public function testGroupsAndNot(): void
    {
        [$sql, $params] = $this->compile(new SqliteDialect(), [['!', ['a', 1]], 'or', [['b', 2], 'and', ['c', 3]]]);

        self::assertSame('(NOT (("a" IS NOT NULL AND "a" = ?)) OR (("b" IS NOT NULL AND "b" = ?) AND ("c" IS NOT NULL AND "c" = ?)))', $sql);
        self::assertSame([1, 2, 3], $params);
    }

    public function testIsoDatesAreNormalised(): void
    {
        [, $params] = $this->compile(new SqliteDialect(), ['d', '>=', '2024-05-01T10:30:00.000Z']);
        self::assertSame(['2024-05-01 10:30:00'], $params);

        [, $params] = $this->compile(new SqliteDialect(), ['d', '>=', '2024-05-01T10:30']);
        self::assertSame(['2024-05-01 10:30:00'], $params);

        [, $params] = $this->compile(new SqliteDialect(), ['d', '>=', '2024-05-01']);
        self::assertSame(['2024-05-01'], $params);
    }

    public function testBooleanValuesBecomeIntegers(): void
    {
        [, $params] = $this->compile(new SqliteDialect(), ['flag', true]);
        self::assertSame([1], $params);
    }

    /**
     * @return iterable<string, array{0: Dialect, 1: string, 2: string}>
     */
    public static function datePartProvider(): iterable
    {
        yield 'sqlite year' => [new SqliteDialect(), 'year', "CAST(strftime('%Y', d) AS INTEGER)"];
        yield 'sqlite quarter' => [new SqliteDialect(), 'quarter', "((CAST(strftime('%m', d) AS INTEGER) + 2) / 3)"];
        yield 'sqlite dow' => [new SqliteDialect(), 'dayOfWeek', "CAST(strftime('%w', d) AS INTEGER)"];
        yield 'mysql month' => [new MySqlDialect(), 'month', 'MONTH(d)'];
        yield 'mysql dow' => [new MySqlDialect(), 'dayOfWeek', '(DAYOFWEEK(d) - 1)'];
        yield 'mysql day' => [new MySqlDialect(), 'day', 'DAYOFMONTH(d)'];
        yield 'pg year' => [new PostgresDialect(), 'year', 'CAST(EXTRACT(YEAR FROM d) AS INTEGER)'];
        yield 'pg dow' => [new PostgresDialect(), 'dayOfWeek', 'CAST(EXTRACT(DOW FROM d) AS INTEGER)'];
    }

    #[DataProvider('datePartProvider')]
    public function testDateParts(Dialect $dialect, string $part, string $expected): void
    {
        self::assertSame($expected, $dialect->datePart($part, 'd'));
    }

    public function testNumericIntervals(): void
    {
        self::assertSame('(CAST((x) * 1.0 / 10 AS INTEGER) * 10)', (new SqliteDialect())->truncateToInterval('x', '10'));
        self::assertSame('(TRUNCATE((x) / 0.5, 0) * 0.5)', (new MySqlDialect())->truncateToInterval('x', '0.5'));
        self::assertSame('(TRUNC(CAST(x AS NUMERIC) / 10) * 10)', (new PostgresDialect())->truncateToInterval('x', '10'));
    }

    public function testIntervalsCannotCarrySql(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SqliteDialect())->truncateToInterval('x', '10); DROP TABLE t; --');
    }

    public function testLimitOffset(): void
    {
        self::assertSame('', (new SqliteDialect())->limitOffset(0, 0));
        self::assertSame(' LIMIT 10 OFFSET 20', (new SqliteDialect())->limitOffset(10, 20));
        self::assertSame(' LIMIT -1 OFFSET 5', (new SqliteDialect())->limitOffset(0, 5));
        self::assertSame(' LIMIT 18446744073709551615 OFFSET 5', (new MySqlDialect())->limitOffset(0, 5));
        self::assertSame(' LIMIT ALL OFFSET 5', (new PostgresDialect())->limitOffset(0, 5));
    }

    public function testPostgresKeepsNullsSmallest(): void
    {
        self::assertSame('ASC NULLS FIRST', (new PostgresDialect())->orderDirection(false));
        self::assertSame('DESC NULLS LAST', (new PostgresDialect())->orderDirection(true));
        self::assertSame('DESC', (new SqliteDialect())->orderDirection(true));
    }

    public function testColumnResolverWhitelist(): void
    {
        $dialect = new SqliteDialect();
        $resolver = new ColumnResolver($dialect, ['Id' => 't.id', 'name' => 'UPPER(t.name)']);

        self::assertSame('t.id', $resolver->resolve('id'), 'whitelist lookup is case-insensitive');
        self::assertSame('UPPER(t.name)', $resolver->resolve('name'));
        self::assertSame(['Id', 'name'], $resolver->knownFields());

        $this->expectException(InvalidArgumentException::class);
        $resolver->resolve('password');
    }

    public function testColumnResolverWithoutWhitelistAcceptsOnlyIdentifiers(): void
    {
        $resolver = new ColumnResolver(new SqliteDialect());

        self::assertSame('"order_no"', $resolver->resolve('order_no'));

        foreach (['a b', 'a.b', 'a;b', '1a', '', 'a"b', 'a)--'] as $bad) {
            try {
                $resolver->resolve($bad);
                self::fail("'$bad' should be rejected");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testDialectDetection(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite missing');
        }

        self::assertInstanceOf(SqliteDialect::class, Dialect::fromPdo(new \PDO('sqlite::memory:')));
    }
}
