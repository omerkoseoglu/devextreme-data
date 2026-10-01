<?php

declare(strict_types=1);

namespace DevExtreme\Data\Tests\Unit;

use DevExtreme\Data\ArraySource;
use DevExtreme\Data\Filter\BinaryExpressionInfo;
use DevExtreme\Data\Filter\CustomFilterCompilers;
use DevExtreme\Data\LoadOptions;
use DevExtreme\Data\PdoSource;
use DevExtreme\Data\Sql\SqlFragment;
use DevExtreme\Data\Support\Accessor;
use DevExtreme\Data\Tests\Support\Fixtures;
use DevExtreme\Data\Tests\Support\PdoFixture;
use PHPUnit\Framework\TestCase;

final class CustomFilterCompilersTest extends TestCase
{
    protected function tearDown(): void
    {
        CustomFilterCompilers::clear();
    }

    private function registerAnyOf(): void
    {
        // ["category", "anyof", ["Books", "Music"]]
        CustomFilterCompilers::registerBinary(static function (BinaryExpressionInfo $info): callable|SqlFragment|null {
            if ($info->operation !== 'anyof' || !is_array($info->value)) {
                return null;
            }

            $values = $info->value;

            if ($info->target === BinaryExpressionInfo::TARGET_SQL) {
                $column = $info->columns?->resolve($info->field) ?? $info->field;

                return new SqlFragment(
                    sprintf('%s IN (%s)', $column, implode(', ', array_fill(0, count($values), '?'))),
                    $values,
                );
            }

            return static fn (mixed $item): bool => in_array(Accessor::read($item, $info->field), $values, true);
        });
    }

    public function testMemoryTarget(): void
    {
        $this->registerAnyOf();

        $result = (new ArraySource(Fixtures::orders()))->load(LoadOptions::fromArray([
            'filter' => ['category', 'anyof', ['Books', 'Music']],
            'select' => ['id'],
        ]));

        self::assertSame([1, 2, 5, 6, 7], array_column($result->data ?? [], 'id'));
    }

    public function testSqlTarget(): void
    {
        $this->registerAnyOf();
        $pdo = PdoFixture::sqlite();
        PdoFixture::createOrders($pdo, Fixtures::orders());

        $result = (new PdoSource($pdo, 'orders', primaryKey: ['id']))->load(LoadOptions::fromArray([
            'filter' => [['category', 'anyof', ['Books', 'Music']], 'and', ['shipped', 0]],
            'select' => ['id'],
        ]));

        self::assertSame([5, 7], array_column($result->data ?? [], 'id'));
    }

    public function testBuiltInOperationsStillWork(): void
    {
        $this->registerAnyOf();

        $result = (new ArraySource(Fixtures::orders()))->load(LoadOptions::fromArray(['filter' => ['id', 3], 'select' => ['id']]));

        self::assertSame([['id' => 3]], $result->data);
    }
}
