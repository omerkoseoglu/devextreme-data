<?php

declare(strict_types=1);

namespace DevExtreme\Data\Tests\Unit;

use DevExtreme\Data\Grouping\GroupKey;
use DevExtreme\Data\GroupingInfo;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GroupKeyTest extends TestCase
{
    private function key(mixed $value, ?string $interval): mixed
    {
        return GroupKey::compute($value, new GroupingInfo('x', false, $interval));
    }

    public function testWithoutIntervalTheValueIsTheKey(): void
    {
        self::assertSame('a', $this->key('a', null));
        self::assertNull($this->key(null, 'year'));
    }

    public function testNumericRanges(): void
    {
        self::assertSame(20, $this->key(27, '10'));
        self::assertSame(0, $this->key(9, '10'));
        self::assertSame(-10, $this->key(-15, '10'), 'truncation follows the dividend sign like C# %');
        self::assertSame(0, $this->key(-5, '10'));
        self::assertEqualsWithDelta(2.5, $this->key(2.7, '0.5'), 1e-9);
        self::assertSame(20, $this->key('27', '10'));
    }

    public function testDateIntervals(): void
    {
        $date = '2024-08-17 15:45:30'; // Saturday

        self::assertSame(2024, $this->key($date, 'year'));
        self::assertSame(3, $this->key($date, 'quarter'));
        self::assertSame(8, $this->key($date, 'month'));
        self::assertSame(17, $this->key($date, 'day'));
        self::assertSame(6, $this->key($date, 'dayOfWeek'));
        self::assertSame(15, $this->key($date, 'hour'));
        self::assertSame(45, $this->key($date, 'minute'));
        self::assertSame(30, $this->key($date, 'second'));
    }

    public function testDateTimeObjectsAndTimestamps(): void
    {
        self::assertSame(2024, $this->key(new \DateTimeImmutable('2024-01-02'), 'year'));
        self::assertSame(1970, $this->key(0, 'year'));
    }

    public function testUnsupportedIntervalThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->key('2024-01-01', 'fortnight');
    }

    public function testNonNumericValueWithNumericIntervalThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->key('abc', '10');
    }

    public function testIdentityTellsNullFromEmptyStringAndNumbersFromStrings(): void
    {
        $ids = array_map(GroupKey::identity(...), [null, '', 0, '0', false, 1, true]);

        self::assertCount(7, array_unique($ids));
    }
}
