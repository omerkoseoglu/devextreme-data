<?php

declare(strict_types=1);

namespace DevExtreme\Data\Tests\Integration;

use DevExtreme\Data\Contracts\DataSourceInterface;
use DevExtreme\Data\LoadOptions;
use DevExtreme\Data\PdoSource;
use DevExtreme\Data\Tests\Support\Fixtures;
use DevExtreme\Data\Tests\Support\PdoFixture;
use DevExtreme\Data\Tests\Support\SourceContractTestCase;
use InvalidArgumentException;
use PDO;

final class PdoSqliteSourceTest extends SourceContractTestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = PdoFixture::sqlite();
    }

    protected function createSource(array $rows): DataSourceInterface
    {
        PdoFixture::createOrders($this->pdo, $rows);

        return new PdoSource($this->pdo, 'orders');
    }

    // ---- SQL source specifics --------------------------------------------------------------

    public function testRejectsInjectionThroughFieldNames(): void
    {
        $source = $this->createSource(Fixtures::orders());

        foreach ([
            ['filter' => ['id; DROP TABLE orders; --', '=', 1]],
            ['sort' => [['selector' => 'id DESC, (SELECT 1)']]],
            ['group' => [['selector' => '1) UNION SELECT 1 --']]],
            ['select' => ['id"']],
            ['totalSummary' => [['selector' => 'amount) FROM orders; --', 'summaryType' => 'sum']]],
        ] as $options) {
            try {
                $source->load(LoadOptions::fromArray($options));
                self::fail('Expected InvalidArgumentException for ' . json_encode($options));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        self::assertSame(8, (int) $this->pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
    }

    public function testFilterValuesAreBoundNotInterpolated(): void
    {
        $source = $this->createSource(Fixtures::orders());

        $result = $source->load(LoadOptions::fromArray(['filter' => ['customer', '=', "x' OR '1'='1"]]));

        self::assertSame([], $result->data);
    }

    public function testColumnWhitelistRestrictsAndMapsFields(): void
    {
        PdoFixture::createOrders($this->pdo, Fixtures::orders());
        $source = new PdoSource($this->pdo, 'orders', columns: ['id' => 'id', 'buyer' => 'customer']);

        $result = $source->load(LoadOptions::fromArray(['filter' => ['buyer', 'Dave'], 'primaryKey' => ['id']]));
        self::assertSame([['id' => 5, 'buyer' => 'Dave']], $result->data);

        $this->expectException(InvalidArgumentException::class);
        $source->load(LoadOptions::fromArray(['filter' => ['amount', '>', 1]]));
    }

    public function testBaseWhereIsAlwaysApplied(): void
    {
        PdoFixture::createOrders($this->pdo, Fixtures::orders());
        $source = new PdoSource($this->pdo, 'orders', where: 'shipped = ?', whereParams: [1]);

        $result = $source->load(LoadOptions::fromArray([
            'filter' => ['category', 'Games'],
            'requireTotalCount' => true,
            'primaryKey' => ['id'],
        ]));

        self::assertSame([4, 8], Fixtures::ids($result->data ?? []));
        self::assertSame(2, $result->totalCount);
    }

    public function testRawFromAllowsJoins(): void
    {
        PdoFixture::createOrders($this->pdo, Fixtures::orders());
        $this->pdo->exec('CREATE TABLE cats (name TEXT, label TEXT)');
        $this->pdo->exec("INSERT INTO cats VALUES ('Books', 'Reading'), ('Games', 'Play'), ('Music', 'Listen')");

        $source = new PdoSource(
            $this->pdo,
            'orders o JOIN cats c ON c.name = o.category',
            columns: ['id' => 'o.id', 'amount' => 'o.amount', 'category.label' => 'c.label'],
            rawFrom: true,
        );

        $result = $source->load(LoadOptions::fromArray([
            'filter' => ['category.label', 'Play'],
            'select' => ['id', 'category.label'],
            'primaryKey' => ['id'],
        ]));

        self::assertSame(
            [['id' => 3, 'category' => ['label' => 'Play']], ['id' => 4, 'category' => ['label' => 'Play']], ['id' => 8, 'category' => ['label' => 'Play']]],
            $result->data,
        );

        $grouped = $source->load(LoadOptions::fromArray([
            'group' => [['selector' => 'category.label', 'isExpanded' => false]],
            'groupSummary' => [['selector' => 'amount', 'summaryType' => 'sum']],
        ]));
        $data = $grouped->toArray()['data'] ?? [];
        self::assertSame(['Listen', 'Play', 'Reading'], array_column($data, 'key'));
        self::assertEquals([50, 140, 90], array_map(static fn (array $g): mixed => $g['summary'][0], $data));
    }

    public function testRawFromCanCarryBindings(): void
    {
        PdoFixture::createOrders($this->pdo, Fixtures::orders());

        $source = new PdoSource(
            $this->pdo,
            '(SELECT * FROM orders WHERE shipped = ? AND qty >= ?) AS s',
            rawFrom: true,
            fromParams: [1, 2],
        );

        $result = $source->load(LoadOptions::fromArray([
            'filter' => ['category', 'Games'],
            'group' => [['selector' => 'category', 'isExpanded' => false]],
            'groupSummary' => [['selector' => 'amount', 'summaryType' => 'sum']],
            'requireTotalCount' => true,
        ]));

        self::assertSame(1, $result->totalCount);
        self::assertEquals([70], array_column($result->toArray()['data'] ?? [], 'summary')[0]);

        $rows = $source->load(LoadOptions::fromArray(['primaryKey' => ['id'], 'take' => 2, 'skip' => 1, 'select' => ['id']]));
        self::assertSame([['id' => 6], ['id' => 8]], $rows->data);
    }

    public function testPaginateViaPrimaryKeyReturnsSamePage(): void
    {
        $source = $this->createSource(Fixtures::orders());

        $page = $source->load(LoadOptions::fromArray([
            'paginateViaPrimaryKey' => true,
            'primaryKey' => ['id'],
            'sort' => [['selector' => 'amount', 'desc' => true]],
            'skip' => 1,
            'take' => 3,
        ]));

        self::assertSame([7, 5, 4], Fixtures::ids($page->data ?? []));
    }

    public function testPaginateViaPrimaryKeyNeedsAKey(): void
    {
        $source = $this->createSource(Fixtures::orders());

        $this->expectException(\LogicException::class);
        $source->load(LoadOptions::fromArray(['paginateViaPrimaryKey' => true, 'take' => 2]));
    }

    public function testRemoteGroupingCanBeDisabled(): void
    {
        $source = $this->createSource(Fixtures::orders());
        $options = [
            'group' => [['selector' => 'category', 'isExpanded' => false]],
            'groupSummary' => [['selector' => 'amount', 'summaryType' => 'sum']],
            'totalSummary' => [['selector' => 'amount', 'summaryType' => 'avg']],
        ];

        $remote = $source->load(LoadOptions::fromArray($options));
        $local = $source->load(LoadOptions::fromArray($options + ['remoteGrouping' => false]));

        self::assertEquals($remote->toArray(), $local->toArray());
    }

    public function testRemoteSelectCanBeDisabled(): void
    {
        $source = $this->createSource(Fixtures::orders());

        $result = $source->load(LoadOptions::fromArray(['select' => ['id'], 'remoteSelect' => false, 'take' => 2, 'primaryKey' => ['id']]));

        self::assertSame([['id' => 1], ['id' => 2]], $result->data);
    }

    public function testRequiresExceptionErrorMode(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);

        $this->expectException(InvalidArgumentException::class);
        new PdoSource($pdo, 'orders');
    }

    public function testRejectsSuspiciousTableNames(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PdoSource($this->pdo, 'orders; DROP TABLE orders');
    }
}
