<?php

declare(strict_types=1);

namespace DevExtreme\Data\Tests\Unit;

use DevExtreme\Data\GroupingInfo;
use DevExtreme\Data\LoadOptions;
use DevExtreme\Data\SortingInfo;
use DevExtreme\Data\SummaryInfo;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LoadOptionsTest extends TestCase
{
    public function testParsesTheParametersSentByTheDevExtremeClient(): void
    {
        $o = LoadOptions::fromArray([
            'requireTotalCount' => 'true',
            'requireGroupCount' => 'false',
            'skip' => '20',
            'take' => '10',
            'sort' => '[{"selector":"name","desc":true},{"selector":"id"}]',
            'group' => '[{"selector":"city","desc":false,"isExpanded":false,"groupInterval":"month"},{"selector":"price","groupInterval":100}]',
            'filter' => '[["price",">",10],"and",["name","contains","a"]]',
            'totalSummary' => '[{"selector":"price","summaryType":"sum"}]',
            'groupSummary' => '[{"selector":"price","summaryType":"avg"}]',
            'select' => '["id","name"]',
        ]);

        self::assertTrue($o->requireTotalCount);
        self::assertFalse($o->requireGroupCount);
        self::assertSame(20, $o->skip);
        self::assertSame(10, $o->take);
        self::assertEquals([new SortingInfo('name', true), new SortingInfo('id', false)], $o->sort);
        self::assertEquals(
            [new GroupingInfo('city', false, 'month', false), new GroupingInfo('price', false, '100', null)],
            $o->group,
        );
        self::assertSame([['price', '>', 10], 'and', ['name', 'contains', 'a']], $o->filter);
        self::assertEquals([new SummaryInfo('price', 'sum')], $o->totalSummary);
        self::assertEquals([new SummaryInfo('price', 'avg')], $o->groupSummary);
        self::assertSame(['id', 'name'], $o->select);
    }

    public function testDefaultsWhenNothingIsSent(): void
    {
        $o = LoadOptions::fromArray([]);

        self::assertFalse($o->requireTotalCount);
        self::assertSame(0, $o->skip);
        self::assertSame(0, $o->take);
        self::assertSame([], $o->sort);
        self::assertNull($o->filter);
        self::assertNull($o->remoteGrouping);
    }

    public function testEmptyStringsAreIgnored(): void
    {
        $o = LoadOptions::fromArray(['skip' => '', 'take' => '', 'filter' => '', 'sort' => '', 'requireTotalCount' => '']);

        self::assertSame(0, $o->skip);
        self::assertNull($o->filter);
        self::assertSame([], $o->sort);
        self::assertFalse($o->requireTotalCount);
    }

    public function testAcceptsAlreadyDecodedValues(): void
    {
        $o = LoadOptions::fromArray([
            'filter' => ['id', 5],
            'sort' => [['selector' => 'id', 'desc' => 'true']],
            'group' => ['selector' => 'id'],
            'requireTotalCount' => true,
        ]);

        self::assertSame(['id', 5], $o->filter);
        self::assertTrue($o->sort[0]->desc);
        self::assertSame('id', $o->group[0]->selector);
        self::assertTrue($o->requireTotalCount);
    }

    public function testGroupIntervalFloatsAreNormalised(): void
    {
        $o = LoadOptions::fromArray(['group' => [['selector' => 'x', 'groupInterval' => 0.5]]]);

        self::assertSame('0.5', $o->group[0]->groupInterval);
    }

    public function testExpandedDefaultsToTrue(): void
    {
        $o = LoadOptions::fromArray(['group' => [['selector' => 'x']]]);

        self::assertNull($o->group[0]->isExpanded);
        self::assertTrue($o->group[0]->getIsExpanded());
    }

    public function testParseWithValueSourceCallback(): void
    {
        $source = ['take' => '3'];

        self::assertSame(3, LoadOptions::parse(static fn (string $key): ?string => $source[$key] ?? null)->take);
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'broken json' => [['filter' => '[["a"']];
        yield 'take not a number' => [['take' => 'many']];
        yield 'bool garbage' => [['requireTotalCount' => 'maybe']];
        yield 'sort without selector' => [['sort' => [['desc' => true]]]];
        yield 'sort not objects' => [['sort' => '["name"]']];
        yield 'summary without type' => [['totalSummary' => [['selector' => 'a']]]];
        yield 'select not strings' => [['select' => [1, 2]]];
    }

    /**
     * @param array<string, mixed> $params
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidProvider')]
    public function testMalformedInputThrows(array $params): void
    {
        $this->expectException(InvalidArgumentException::class);

        LoadOptions::fromArray($params);
    }
}
