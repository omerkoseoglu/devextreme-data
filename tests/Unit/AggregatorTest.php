<?php

declare(strict_types=1);

namespace DevExtreme\Data\Tests\Unit;

use DevExtreme\Data\Aggregation\Aggregator;
use DevExtreme\Data\Aggregation\CustomAggregators;
use DevExtreme\Data\ArraySource;
use DevExtreme\Data\LoadOptions;
use DevExtreme\Data\Support\Accessor;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class AggregatorTest extends TestCase
{
    protected function tearDown(): void
    {
        CustomAggregators::clear();
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<mixed>
     */
    private function summarize(array $rows, string $type, string $selector = 'v'): array
    {
        $o = LoadOptions::fromArray(['totalSummary' => [['selector' => $selector, 'summaryType' => $type]]]);

        return (new ArraySource($rows))->load($o)->summary ?? [];
    }

    public function testSumKeepsIntegersAndSkipsNonNumeric(): void
    {
        self::assertSame([6], $this->summarize([['v' => 1], ['v' => 2], ['v' => 3]], 'sum'));
        self::assertSame([4.5], $this->summarize([['v' => 1], ['v' => 3.5], ['v' => null], ['v' => 'abc']], 'sum'));
        self::assertSame([3], $this->summarize([['v' => '1'], ['v' => '2']], 'sum'));
    }

    public function testAvgDividesByNonNullCount(): void
    {
        self::assertSame([2], $this->summarize([['v' => 1], ['v' => 3], ['v' => null]], 'avg'));
        self::assertSame([null], $this->summarize([['v' => null]], 'avg'));
    }

    public function testMinMaxOnNumbersStringsAndDates(): void
    {
        self::assertSame([1], $this->summarize([['v' => 3], ['v' => 1], ['v' => null]], 'min'));
        self::assertSame([3], $this->summarize([['v' => 3], ['v' => 1]], 'max'));
        self::assertSame(['apple'], $this->summarize([['v' => 'banana'], ['v' => 'apple']], 'min'));

        $early = new \DateTimeImmutable('2020-01-01');
        $late = new \DateTimeImmutable('2021-01-01');
        self::assertSame([$late], $this->summarize([['v' => $early], ['v' => $late]], 'max'));
    }

    public function testCountCountsEveryItemIncludingNulls(): void
    {
        self::assertSame([3], $this->summarize([['v' => 1], ['v' => null], ['v' => 2]], 'count'));
    }

    public function testUnknownSummaryTypeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->summarize([['v' => 1]], 'median');
    }

    public function testCustomAggregator(): void
    {
        CustomAggregators::register('concat', static fn (): Aggregator => new class () extends Aggregator {
            private string $text = '';

            public function step(mixed $item, string $selector): void
            {
                $this->text .= (string) Accessor::read($item, $selector);
            }

            public function finish(): string
            {
                return $this->text;
            }
        });

        self::assertSame(['abc'], $this->summarize([['v' => 'a'], ['v' => 'b'], ['v' => 'c']], 'concat'));

        $grouped = (new ArraySource([['g' => 1, 'v' => 'a'], ['g' => 1, 'v' => 'b'], ['g' => 2, 'v' => 'c']]))->load(LoadOptions::fromArray([
            'group' => [['selector' => 'g']],
            'groupSummary' => [['selector' => 'v', 'summaryType' => 'concat']],
        ]));
        self::assertSame(['ab'], $grouped->toArray()['data'][0]['summary']);
    }

    public function testBuiltInNamesCannotBeOverridden(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CustomAggregators::register('sum', static fn (): Aggregator => throw new \LogicException());
    }
}
