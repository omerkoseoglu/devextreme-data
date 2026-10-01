<?php

declare(strict_types=1);

namespace DevExtreme\Data\Tests\Unit;

use DevExtreme\Data\Select\SelectHelper;
use PHPUnit\Framework\TestCase;

final class SelectHelperTest extends TestCase
{
    public function testNestsDottedPaths(): void
    {
        $rows = [['id' => 1, 'customer' => ['name' => 'A', 'city' => 'X'], 'extra' => true]];

        self::assertSame(
            [['id' => 1, 'customer' => ['name' => 'A', 'city' => 'X']]],
            SelectHelper::evaluate($rows, ['id', 'customer.name', 'customer.city']),
        );
    }

    public function testMissingValuesBecomeNull(): void
    {
        self::assertSame([['a' => null, 'b' => ['c' => null]]], SelectHelper::evaluate([['x' => 1]], ['a', 'b.c']));
    }

    public function testFlatKeysWithDotsAreNested(): void
    {
        self::assertSame(
            [['customer' => ['name' => 'Z']]],
            SelectHelper::evaluate([['customer.name' => 'Z']], ['customer.name']),
        );
    }

    public function testObjects(): void
    {
        self::assertSame([['id' => 7]], SelectHelper::evaluate([(object) ['id' => 7, 'x' => 1]], ['id']));
    }
}
