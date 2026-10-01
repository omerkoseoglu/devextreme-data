<?php

declare(strict_types=1);

namespace DevExtreme\Data\Tests\Unit;

use ArrayObject;
use DevExtreme\Data\Support\Accessor;
use PHPUnit\Framework\TestCase;

final class AccessorTest extends TestCase
{
    public function testArrays(): void
    {
        self::assertSame(1, Accessor::read(['a' => 1], 'a'));
        self::assertNull(Accessor::read(['a' => 1], 'b'));
        self::assertSame(2, Accessor::read(['a' => ['b' => 2]], 'a.b'));
        self::assertNull(Accessor::read(['a' => null], 'a.b'));
    }

    public function testCaseInsensitiveFallback(): void
    {
        self::assertSame(1, Accessor::read(['Name' => 1], 'name'));
        self::assertSame(1, Accessor::read((object) ['Name' => 1], 'name'));
    }

    public function testExactKeyWinsAndDottedKeysWork(): void
    {
        self::assertSame('flat', Accessor::read(['a.b' => 'flat', 'a' => ['b' => 'nested']], 'a.b'));
    }

    public function testThisReturnsTheItem(): void
    {
        self::assertSame(5, Accessor::read(5, 'this'));
    }

    public function testArrayAccessAndObjects(): void
    {
        self::assertSame(1, Accessor::read(new ArrayObject(['a' => 1]), 'a'));

        $object = new class () {
            public int $public = 1;
            private int $secret = 2;

            public function getComputed(): int
            {
                return $this->secret * 10;
            }
        };

        self::assertSame(1, Accessor::read($object, 'public'));
        self::assertSame(20, Accessor::read($object, 'computed'));
        self::assertNull(Accessor::read($object, 'secret'));
    }

    public function testNullItem(): void
    {
        self::assertNull(Accessor::read(null, 'a.b'));
    }
}
