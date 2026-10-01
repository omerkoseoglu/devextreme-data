<?php

declare(strict_types=1);

namespace DevExtreme\Data\Tests\Support;

use DevExtreme\Data\Contracts\DataSourceInterface;
use DevExtreme\Data\LoadOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Behaviour every data source must implement identically. Expectations are hand-computed from {@see Fixtures::orders()}.
 */
abstract class SourceContractTestCase extends TestCase
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    abstract protected function createSource(array $rows): DataSourceInterface;

    /**
     * @param array<string, mixed> $options request-style parameters; arrays are fine, no need to JSON-encode
     *
     * @return array<string, mixed>
     */
    protected function load(array $options = []): array
    {
        $options += ['primaryKey' => ['id']];

        return $this->createSource(Fixtures::orders())->load(LoadOptions::fromArray($options))->toArray();
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<int>
     */
    protected function ids(array $options = []): array
    {
        /** @var list<mixed> $data */
        $data = $this->load($options)['data'];

        return Fixtures::ids($data);
    }

    public function testReturnsEverythingByDefault(): void
    {
        $result = $this->load();

        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8], Fixtures::ids($result['data']));
        self::assertSame(-1, $result['totalCount']);
        self::assertSame(-1, $result['groupCount']);
        self::assertArrayNotHasKey('summary', $result);
    }

    public function testSkipTakeAndTotalCount(): void
    {
        $result = $this->load(['skip' => 2, 'take' => 3, 'requireTotalCount' => true]);

        self::assertSame([3, 4, 5], Fixtures::ids($result['data']));
        self::assertSame(8, $result['totalCount']);
    }

    public function testSkipWithoutTake(): void
    {
        self::assertSame([7, 8], $this->ids(['skip' => 6]));
    }

    public function testTotalCountIsTheFilteredCountNotThePageSize(): void
    {
        $result = $this->load(['filter' => ['category', 'Games'], 'take' => 1, 'requireTotalCount' => true]);

        self::assertSame([3], Fixtures::ids($result['data']));
        self::assertSame(3, $result['totalCount']);
    }

    public function testCountQuery(): void
    {
        $result = $this->load(['isCountQuery' => true, 'filter' => ['shipped', 1]]);

        self::assertSame(5, $result['totalCount']);
        self::assertNull($result['data']);
    }

    public function testSortDescending(): void
    {
        self::assertSame([8, 7, 6, 5, 4, 3, 2, 1], $this->ids(['sort' => [['selector' => 'id', 'desc' => true]]]));
    }

    public function testMultiKeySort(): void
    {
        $ids = $this->ids(['sort' => [
            ['selector' => 'category'],
            ['selector' => 'amount', 'desc' => true],
        ]]);

        // Books: 60, 20, 10 | Games: 70, 40, 30 | Music: 50, null
        self::assertSame([7, 2, 1, 8, 4, 3, 5, 6], $ids);
    }

    public function testNullsSortFirstAscending(): void
    {
        self::assertSame(6, $this->ids(['sort' => [['selector' => 'amount']]])[0]);

        $descending = $this->ids(['sort' => [['selector' => 'amount', 'desc' => true]]]);
        self::assertSame(6, $descending[array_key_last($descending)]);
    }

    public function testDefaultSortAndPrimaryKeyBreakTies(): void
    {
        // equal categories are ordered by the primary key that is appended to the sort
        self::assertSame([1, 2, 7, 3, 4, 8, 5, 6], $this->ids(['sort' => [['selector' => 'category']]]));
    }

    /**
     * @return iterable<string, array{0: list<mixed>, 1: list<int>}>
     */
    public static function filterProvider(): iterable
    {
        yield 'implicit equals' => [['category', 'Music'], [5, 6]];
        yield 'equals' => [['qty', '=', 1], [2, 4]];
        yield 'not equals includes nulls' => [['amount', '<>', 20], [1, 3, 4, 5, 6, 7, 8]];
        yield 'greater' => [['amount', '>', 40], [5, 7, 8]];
        yield 'greater or equal' => [['amount', '>=', 40], [4, 5, 7, 8]];
        yield 'less' => [['amount', '<', 30], [1, 2]];
        yield 'less or equal' => [['amount', '<=', 30], [1, 2, 3]];
        yield 'null never matches inequality' => [['amount', '>', 0], [1, 2, 3, 4, 5, 7, 8]];
        yield 'equals null' => [['note', '=', null], [1, 3, 6]];
        yield 'not equals null' => [['note', '<>', null], [2, 4, 5, 7, 8]];
        yield 'contains' => [['customer', 'contains', 'ar'], [3]];
        yield 'contains is case-insensitive' => [['note', 'contains', 'GIFT'], [2, 5]];
        yield 'notcontains' => [['note', 'notcontains', 'gift'], [1, 3, 4, 6, 7, 8]];
        yield 'startswith' => [['customer', 'startswith', 'al'], [1, 4]];
        yield 'endswith' => [['customer', 'endswith', 'E'], [1, 4, 5, 6, 8]];
        yield 'like wildcards are literal' => [['note', 'contains', '%'], [7]];
        yield 'underscore is literal' => [['note', 'contains', 'a_b'], [8]];
        yield 'and' => [[['category', 'Books'], 'and', ['amount', '>', 15]], [2, 7]];
        yield 'implicit and' => [[['category', 'Books'], ['amount', '>', 15]], [2, 7]];
        yield 'or' => [[['category', 'Music'], 'or', ['qty', '>=', 6]], [5, 6, 8]];
        yield 'nested groups' => [[[['category', 'Books'], 'or', ['category', 'Games']], 'and', ['shipped', 0]], [3, 7]];
        yield 'not' => [['!', ['category', 'Books']], [3, 4, 5, 6, 8]];
        yield 'not of inequality keeps nulls' => [['!', ['amount', '>', 25]], [1, 2, 6]];
        yield 'date range (iso with time zone)' => [[['ordered_at', '>=', '2025-01-10T08:00:00.000Z'], 'and', ['ordered_at', '<', '2025-07-04T00:00:00Z']], [5, 6]];
        yield 'date only' => [['ordered_at', '>=', '2025-07-04'], [7, 8]];
        yield 'numeric string value' => [['qty', '=', '5'], [5]];
    }

    /**
     * @param list<mixed> $filter
     * @param list<int>   $expected
     */
    #[DataProvider('filterProvider')]
    public function testFilter(array $filter, array $expected): void
    {
        self::assertSame($expected, $this->ids(['filter' => $filter]));
    }

    public function testFilterAcceptsJsonString(): void
    {
        self::assertSame([5, 6], $this->ids(['filter' => '["category","=","Music"]']));
    }

    public function testStringToLowerMakesEqualityCaseInsensitive(): void
    {
        self::assertSame([1, 4], $this->ids(['filter' => ['customer', '=', 'ALICE'], 'stringToLower' => true]));
    }

    public function testMixingAndOrIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->load(['filter' => [['qty', 1], 'and', ['qty', 2], 'or', ['qty', 3]]]);
    }

    public function testUnknownOperationIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->load(['filter' => ['qty', 'approx', 1]]);
    }

    public function testSelect(): void
    {
        $result = $this->load(['select' => ['id', 'customer'], 'take' => 2]);

        self::assertSame([['id' => 1, 'customer' => 'Alice'], ['id' => 2, 'customer' => 'bob']], $result['data']);
    }

    public function testPreSelectLimitsSelect(): void
    {
        $result = $this->load(['preSelect' => ['id', 'customer'], 'select' => ['customer', 'amount'], 'take' => 1]);

        self::assertSame([['customer' => 'Alice']], $result['data']);
    }

    public function testTotalSummary(): void
    {
        $result = $this->load([
            'take' => 2,
            'totalSummary' => [
                ['selector' => 'amount', 'summaryType' => 'sum'],
                ['selector' => 'amount', 'summaryType' => 'min'],
                ['selector' => 'amount', 'summaryType' => 'max'],
                ['selector' => 'amount', 'summaryType' => 'avg'],
                ['selector' => 'amount', 'summaryType' => 'count'],
                ['selector' => 'qty', 'summaryType' => 'sum'],
            ],
        ]);

        self::assertSame([1, 2], Fixtures::ids($result['data']), 'summary must not affect paging');
        self::assertEquals([280, 10, 70, 40, 8, 24], $result['summary']);
    }

    public function testTotalSummaryRespectsFilter(): void
    {
        $result = $this->load([
            'filter' => ['category', 'Books'],
            'totalSummary' => [['selector' => 'amount', 'summaryType' => 'sum'], ['selector' => 'id', 'summaryType' => 'count']],
        ]);

        self::assertEquals([90, 3], $result['summary']);
    }

    public function testCountOnlySummaryAnswersFromTotalCount(): void
    {
        $result = $this->load(['totalSummary' => [['summaryType' => 'count']], 'filter' => ['shipped', 0], 'take' => 1]);

        self::assertSame([3], $result['summary']);
    }

    public function testSummaryQuery(): void
    {
        $result = $this->load([
            'isSummaryQuery' => true,
            'totalSummary' => [['selector' => 'amount', 'summaryType' => 'sum']],
            'filter' => ['category', 'Games'],
        ]);

        self::assertEquals([140], $result['summary']);
        self::assertNull($result['data']);
    }

    public function testEmptyResultSummary(): void
    {
        $result = $this->load([
            'filter' => ['category', 'Nothing'],
            'totalSummary' => [
                ['selector' => 'amount', 'summaryType' => 'sum'],
                ['selector' => 'amount', 'summaryType' => 'avg'],
                ['selector' => 'amount', 'summaryType' => 'min'],
                ['selector' => 'amount', 'summaryType' => 'count'],
            ],
        ]);

        self::assertSame([], $result['data']);
        self::assertSame([null, null, null, 0], $result['summary']);
    }

    public function testExpandedGroup(): void
    {
        $result = $this->load([
            'group' => [['selector' => 'category']],
            'requireTotalCount' => true,
            'requireGroupCount' => true,
        ]);

        self::assertSame(3, $result['groupCount']);
        self::assertSame(8, $result['totalCount']);
        self::assertSame(['Books', 'Games', 'Music'], array_column($result['data'], 'key'));
        self::assertSame([1, 2, 7], Fixtures::ids($result['data'][0]['items']));
        self::assertArrayNotHasKey('count', $result['data'][0]);
    }

    public function testGroupsAreSortedByGroupSelectorDirection(): void
    {
        $result = $this->load(['group' => [['selector' => 'category', 'desc' => true]]]);

        self::assertSame(['Music', 'Games', 'Books'], array_column($result['data'], 'key'));
    }

    public function testNestedGroupsWithSummaries(): void
    {
        $result = $this->load([
            'group' => [['selector' => 'category'], ['selector' => 'shipped']],
            'groupSummary' => [['selector' => 'amount', 'summaryType' => 'sum'], ['summaryType' => 'count']],
            'totalSummary' => [['selector' => 'amount', 'summaryType' => 'sum']],
        ]);

        [$books, $games, $music] = $result['data'];

        self::assertEquals([90, 3], $books['summary']);
        self::assertEquals([140, 3], $games['summary']);
        self::assertEquals([50, 2], $music['summary']);
        self::assertEquals([280], $result['summary']);

        // Books: shipped 0 => id 7 ; shipped 1 => ids 1, 2
        self::assertEquals([0, 1], array_column($books['items'], 'key'));
        self::assertEquals([60, 1], $books['items'][0]['summary']);
        self::assertEquals([30, 2], $books['items'][1]['summary']);
        self::assertSame([1, 2], Fixtures::ids($books['items'][1]['items']));
    }

    public function testGroupPagingCountsGroupsNotRows(): void
    {
        $result = $this->load([
            'group' => [['selector' => 'category']],
            'skip' => 1,
            'take' => 1,
            'requireTotalCount' => true,
            'requireGroupCount' => true,
            'groupSummary' => [['summaryType' => 'count']],
        ]);

        self::assertSame(['Games'], array_column($result['data'], 'key'));
        self::assertSame(3, $result['groupCount']);
        self::assertSame(8, $result['totalCount']);
        self::assertSame([3, 4, 8], Fixtures::ids($result['data'][0]['items']));
        self::assertSame([3], $result['data'][0]['summary']);
    }

    public function testFilterThenGroup(): void
    {
        $result = $this->load(['filter' => ['amount', '>=', 50], 'group' => [['selector' => 'category']]]);

        self::assertSame(['Books', 'Games', 'Music'], array_column($result['data'], 'key'));
        self::assertSame([7], Fixtures::ids($result['data'][0]['items']));
    }

    public function testGroupKeyCanBeNull(): void
    {
        $result = $this->load(['group' => [['selector' => 'note', 'isExpanded' => false]]]);

        self::assertNull($result['data'][0]['key']);
        self::assertSame(3, $result['data'][0]['count']);
    }

    public function testCollapsedGroupReturnsCounts(): void
    {
        $result = $this->load([
            'group' => [['selector' => 'category', 'isExpanded' => false]],
            'requireTotalCount' => true,
            'requireGroupCount' => true,
        ]);

        self::assertSame(
            [
                ['key' => 'Books', 'items' => null, 'count' => 3],
                ['key' => 'Games', 'items' => null, 'count' => 3],
                ['key' => 'Music', 'items' => null, 'count' => 2],
            ],
            $result['data'],
        );
        self::assertSame(3, $result['groupCount']);
        self::assertSame(8, $result['totalCount']);
    }

    public function testCollapsedGroupSummaries(): void
    {
        $result = $this->load([
            'group' => [['selector' => 'category', 'isExpanded' => false]],
            'groupSummary' => [
                ['selector' => 'amount', 'summaryType' => 'sum'],
                ['selector' => 'amount', 'summaryType' => 'min'],
                ['selector' => 'amount', 'summaryType' => 'max'],
                ['selector' => 'amount', 'summaryType' => 'avg'],
                ['selector' => 'amount', 'summaryType' => 'count'],
            ],
            'totalSummary' => [
                ['selector' => 'amount', 'summaryType' => 'sum'],
                ['selector' => 'amount', 'summaryType' => 'avg'],
            ],
        ]);

        self::assertEquals([90, 10, 60, 30, 3], $result['data'][0]['summary']);
        self::assertEquals([140, 30, 70, 140 / 3, 3], $result['data'][1]['summary']);
        self::assertEquals([50, 50, 50, 50, 2], $result['data'][2]['summary']);
        self::assertEquals([280, 40], $result['summary']);
    }

    public function testCollapsedNestedGroups(): void
    {
        $result = $this->load([
            'group' => [['selector' => 'category'], ['selector' => 'shipped', 'isExpanded' => false]],
            'groupSummary' => [['selector' => 'qty', 'summaryType' => 'sum']],
        ]);

        $books = $result['data'][0];
        self::assertSame('Books', $books['key']);
        self::assertEquals([7], $books['summary']);
        self::assertEquals(
            [['key' => 0, 'items' => null, 'count' => 1, 'summary' => [4]], ['key' => 1, 'items' => null, 'count' => 2, 'summary' => [3]]],
            $books['items'],
        );
    }

    public function testCollapsedGroupPaging(): void
    {
        $result = $this->load([
            'group' => [['selector' => 'category', 'isExpanded' => false]],
            'skip' => 1,
            'take' => 1,
            'requireTotalCount' => true,
            'requireGroupCount' => true,
            'totalSummary' => [['selector' => 'amount', 'summaryType' => 'sum']],
        ]);

        self::assertSame([['key' => 'Games', 'items' => null, 'count' => 3]], $result['data']);
        self::assertSame(3, $result['groupCount']);
        self::assertSame(8, $result['totalCount']);
        self::assertEquals([280], $result['summary'], 'totals cover all groups, not the page');
    }

    public function testGroupByYear(): void
    {
        $result = $this->load(['group' => [['selector' => 'ordered_at', 'groupInterval' => 'year', 'isExpanded' => false]]]);

        self::assertSame([['key' => 2024, 'items' => null, 'count' => 4], ['key' => 2025, 'items' => null, 'count' => 4]], $result['data']);
    }

    public function testGroupByMonthIsOrderedByMonthNumber(): void
    {
        $result = $this->load(['group' => [['selector' => 'ordered_at', 'groupInterval' => 'month', 'isExpanded' => false]]]);

        self::assertSame([1, 2, 3, 5, 7, 11], array_column($result['data'], 'key'));
        self::assertSame([2, 2, 1, 1, 1, 1], array_column($result['data'], 'count'));
    }

    public function testNestedYearMonthGrouping(): void
    {
        $result = $this->load(['group' => [
            ['selector' => 'ordered_at', 'groupInterval' => 'year'],
            ['selector' => 'ordered_at', 'groupInterval' => 'month', 'isExpanded' => false],
        ]]);

        self::assertSame([2024, 2025], array_column($result['data'], 'key'));
        self::assertSame([1, 2, 5], array_column($result['data'][0]['items'], 'key'));
        self::assertSame([1, 3, 7, 11], array_column($result['data'][1]['items'], 'key'));
    }

    #[DataProvider('dateIntervalProvider')]
    public function testDateIntervals(string $interval, array $expectedKeys): void
    {
        $result = $this->load(['group' => [['selector' => 'ordered_at', 'groupInterval' => $interval, 'isExpanded' => false]]]);

        self::assertSame($expectedKeys, array_column($result['data'], 'key'));
    }

    /**
     * @return iterable<string, array{0: string, 1: list<int>}>
     */
    public static function dateIntervalProvider(): iterable
    {
        yield 'quarter' => ['quarter', [1, 2, 3, 4]];
        yield 'day' => ['day', [1, 4, 5, 10, 15, 20, 25, 30]];
        yield 'hour' => ['hour', [8, 9, 10, 11, 12, 14, 18, 23]];
        yield 'dayOfWeek' => ['dayOfWeek', [0, 1, 2, 5, 6]];
    }

    public function testNumericGroupInterval(): void
    {
        $result = $this->load(['group' => [['selector' => 'amount', 'groupInterval' => 25, 'isExpanded' => false]]]);

        // null, 0 (10, 20), 25 (30, 40), 50 (50, 60, 70)
        self::assertEquals([null, 0, 25, 50], array_column($result['data'], 'key'));
        self::assertSame([1, 2, 2, 3], array_column($result['data'], 'count'));
    }

    public function testLoadResultIsJsonSerializable(): void
    {
        $json = json_encode($this->createSource(Fixtures::orders())->load(LoadOptions::fromArray([
            'take' => 1,
            'group' => [['selector' => 'category']],
        ])), JSON_THROW_ON_ERROR);

        $decoded = json_decode($json, true);
        self::assertSame('Books', $decoded['data'][0]['key']);
        self::assertSame(-1, $decoded['totalCount']);
    }
}
