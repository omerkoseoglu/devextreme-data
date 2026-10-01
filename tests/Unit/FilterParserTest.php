<?php

declare(strict_types=1);

namespace DevExtreme\Data\Tests\Unit;

use DevExtreme\Data\Filter\Comparison;
use DevExtreme\Data\Filter\FilterParser;
use DevExtreme\Data\Filter\Logical;
use DevExtreme\Data\Filter\Not;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FilterParserTest extends TestCase
{
    public function testExplicitOperation(): void
    {
        $node = FilterParser::parse(['Price', '>=', 10]);

        self::assertInstanceOf(Comparison::class, $node);
        self::assertSame('Price', $node->field);
        self::assertSame('>=', $node->operation);
        self::assertSame(10, $node->value);
    }

    public function testOperationIsLowerCased(): void
    {
        $node = FilterParser::parse(['name', 'Contains', 'x']);

        self::assertInstanceOf(Comparison::class, $node);
        self::assertSame('contains', $node->operation);
    }

    public function testImplicitEquals(): void
    {
        $node = FilterParser::parse(['id', 5]);

        self::assertInstanceOf(Comparison::class, $node);
        self::assertSame('=', $node->operation);
        self::assertSame(5, $node->value);
    }

    public function testAndGroup(): void
    {
        $node = FilterParser::parse([['a', 1], 'and', ['b', 2], 'and', ['c', 3]]);

        self::assertInstanceOf(Logical::class, $node);
        self::assertTrue($node->isAnd);
        self::assertCount(3, $node->operands);
    }

    public function testOrGroup(): void
    {
        $node = FilterParser::parse([['a', 1], 'or', ['b', 2]]);

        self::assertInstanceOf(Logical::class, $node);
        self::assertFalse($node->isAnd);
    }

    public function testGroupsWithoutConnectorAreAnded(): void
    {
        $node = FilterParser::parse([['a', 1], ['b', 2]]);

        self::assertInstanceOf(Logical::class, $node);
        self::assertTrue($node->isAnd);
    }

    public function testAmpersandConnector(): void
    {
        $node = FilterParser::parse([['a', 1], '&', ['b', 2]]);

        self::assertInstanceOf(Logical::class, $node);
        self::assertTrue($node->isAnd);
    }

    public function testSingleOperandGroupIsUnwrapped(): void
    {
        self::assertInstanceOf(Comparison::class, FilterParser::parse([['a', 1]]));
    }

    public function testUnary(): void
    {
        $node = FilterParser::parse(['!', ['a', 1]]);

        self::assertInstanceOf(Not::class, $node);
        self::assertInstanceOf(Comparison::class, $node->operand);
    }

    public function testNestedUnaryInsideGroup(): void
    {
        $node = FilterParser::parse([['!', ['a', 1]], 'and', ['b', 2]]);

        self::assertInstanceOf(Logical::class, $node);
        self::assertInstanceOf(Not::class, $node->operands[0]);
    }

    /**
     * @return iterable<string, array{0: array<mixed>}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'mixed and/or' => [[['a', 1], 'and', ['b', 2], 'or', ['c', 3]]];
        yield 'mixed or/and' => [[['a', 1], 'or', ['b', 2], 'and', ['c', 3]]];
        yield 'empty' => [[]];
        yield 'one element' => [['a']];
        yield 'unary without operand' => [['!']];
        yield 'associative array' => [['field' => 'a', 'value' => 1]];
    }

    /**
     * @param array<mixed> $filter
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidProvider')]
    public function testInvalidFilters(array $filter): void
    {
        $this->expectException(InvalidArgumentException::class);

        FilterParser::parse($filter);
    }
}
