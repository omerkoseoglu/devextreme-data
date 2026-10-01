<?php

declare(strict_types=1);

namespace DevExtreme\Data\Tests\Integration;

use DevExtreme\Data\ArraySource;
use DevExtreme\Data\Contracts\DataSourceInterface;
use DevExtreme\Data\DataSourceLoader;
use DevExtreme\Data\LoadOptions;
use DevExtreme\Data\Tests\Support\Fixtures;
use DevExtreme\Data\Tests\Support\SourceContractTestCase;

final class ArraySourceTest extends SourceContractTestCase
{
    protected function createSource(array $rows): DataSourceInterface
    {
        return new ArraySource($rows);
    }

    public function testWorksWithObjects(): void
    {
        $objects = array_map(static fn (array $row): object => (object) $row, Fixtures::orders());

        $result = DataSourceLoader::load($objects, [
            'filter' => ['category', 'Games'],
            'sort' => [['selector' => 'amount', 'desc' => true]],
            'select' => ['id'],
        ]);

        self::assertSame([['id' => 8], ['id' => 4], ['id' => 3]], $result->data);
    }

    public function testWorksWithGettersAndDottedPaths(): void
    {
        $rows = [
            new class () {
                public function getName(): string
                {
                    return 'x';
                }

                public function isActive(): bool
                {
                    return true;
                }

                /** @var array<string, string> */
                public array $address = ['city' => 'Izmir'];
            },
        ];

        self::assertCount(1, DataSourceLoader::load($rows, ['filter' => ['name', 'x']])->data ?? []);
        self::assertCount(1, DataSourceLoader::load($rows, ['filter' => ['active', true]])->data ?? []);
        self::assertCount(1, DataSourceLoader::load($rows, ['filter' => ['address.city', 'izmir']])->data ?? []);
        self::assertSame(
            [['address' => ['city' => 'Izmir']]],
            DataSourceLoader::load($rows, ['select' => ['address.city']])->data,
        );
    }

    public function testWorksWithGenerators(): void
    {
        $generator = (static function () {
            yield ['id' => 1];
            yield ['id' => 2];
        })();

        self::assertSame([['id' => 2]], DataSourceLoader::load($generator, ['filter' => ['id', '>', 1]])->data);
    }

    public function testDateTimeValues(): void
    {
        $rows = [
            ['id' => 1, 'at' => new \DateTimeImmutable('2024-03-10 12:00:00')],
            ['id' => 2, 'at' => new \DateTimeImmutable('2025-03-10 12:00:00')],
        ];

        self::assertSame([2], Fixtures::ids(DataSourceLoader::load($rows, ['filter' => ['at', '>', '2025-01-01T00:00:00']])->data ?? []));

        $grouped = DataSourceLoader::load($rows, ['group' => [['selector' => 'at', 'groupInterval' => 'year', 'isExpanded' => false]]]);
        self::assertSame([2024, 2025], array_column($grouped->toArray()['data'] ?? [], 'key'));
    }

    public function testStringToLowerCanBeDisabled(): void
    {
        $result = DataSourceLoader::load(Fixtures::orders(), ['filter' => ['customer', 'alice'], 'stringToLower' => false]);

        self::assertSame([4], Fixtures::ids($result->data ?? []));
    }

    public function testLoadFromRequest(): void
    {
        $result = DataSourceLoader::loadFromRequest(Fixtures::orders(), ['take' => '2', 'requireTotalCount' => 'true']);

        self::assertCount(2, $result->data ?? []);
        self::assertSame(8, $result->totalCount);
    }

    public function testAcceptsLoadOptionsInstance(): void
    {
        $options = new LoadOptions();
        $options->take = 1;

        self::assertCount(1, DataSourceLoader::load(Fixtures::orders(), $options)->data ?? []);
    }
}
