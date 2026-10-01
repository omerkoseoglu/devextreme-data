<?php

declare(strict_types=1);

namespace DevExtreme\Data\Tests\Unit;

use DevExtreme\Data\Support\Compare;
use PHPUnit\Framework\TestCase;

final class CompareTest extends TestCase
{
    public function testNullIsSmallest(): void
    {
        self::assertSame(-1, Compare::compare(null, 0));
        self::assertSame(1, Compare::compare('', null));
        self::assertSame(0, Compare::compare(null, null));
    }

    public function testNumericStringsCompareNumerically(): void
    {
        self::assertSame(1, Compare::compare('10', '9'));
    }

    public function testStringsCompareCaseInsensitivelyWithTieBreak(): void
    {
        self::assertSame(-1, Compare::compare('apple', 'Banana'));
        self::assertNotSame(0, Compare::compare('a', 'A'));
    }

    public function testDates(): void
    {
        self::assertSame(-1, Compare::compare(new \DateTimeImmutable('2020-01-01'), '2021-01-01'));
    }

    public function testFilterComparisonConvertsTheClientValue(): void
    {
        self::assertSame(0, Compare::forFilter(5, '5', false));
        self::assertSame(1, Compare::forFilter(5.5, 5, false));
        self::assertNull(Compare::forFilter(5, 'abc', false));
        self::assertSame(0, Compare::forFilter(true, 'true', false));
        self::assertNull(Compare::forFilter(true, 'perhaps', false));
        self::assertSame(0, Compare::forFilter('ABC', 'abc', true));
        self::assertNotSame(0, Compare::forFilter('ABC', 'abc', false));
        self::assertSame(1, Compare::forFilter('10', 9, false));
        self::assertSame(0, Compare::forFilter(new \DateTimeImmutable('2020-01-01 00:00:00 UTC'), '2020-01-01T00:00:00Z', false));
    }

    public function testDateStringsCompareChronologically(): void
    {
        // a plain string comparison would get this wrong because of the "T" vs " " separator
        self::assertSame(1, Compare::forFilter('2025-01-10 08:00:00', '2025-01-10T00:00:00.000Z', false));
        self::assertSame(0, Compare::forFilter('2025-01-10 00:00:00', '2025-01-10', false));
    }
}
