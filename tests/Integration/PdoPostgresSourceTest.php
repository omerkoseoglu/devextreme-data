<?php

declare(strict_types=1);

namespace DevExtreme\Data\Tests\Integration;

use DevExtreme\Data\Contracts\DataSourceInterface;
use DevExtreme\Data\PdoSource;
use DevExtreme\Data\Tests\Support\PdoFixture;
use DevExtreme\Data\Tests\Support\SourceContractTestCase;
use PDO;

/**
 * Runs the shared contract against a real PostgreSQL server.
 * Enabled by DEVEXTREME_TEST_PGSQL_DSN (see phpunit.xml.dist); skipped otherwise.
 */
final class PdoPostgresSourceTest extends SourceContractTestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $dsn = (string) getenv('DEVEXTREME_TEST_PGSQL_DSN');
        if ($dsn === '' || !extension_loaded('pdo_pgsql')) {
            self::markTestSkipped('Set DEVEXTREME_TEST_PGSQL_DSN to run PostgreSQL tests.');
        }

        $this->pdo = new PDO(
            $dsn,
            (string) (getenv('DEVEXTREME_TEST_PGSQL_USER') ?: 'postgres'),
            (string) getenv('DEVEXTREME_TEST_PGSQL_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    protected function createSource(array $rows): DataSourceInterface
    {
        PdoFixture::createOrders($this->pdo, $rows);

        return new PdoSource($this->pdo, 'orders');
    }
}
