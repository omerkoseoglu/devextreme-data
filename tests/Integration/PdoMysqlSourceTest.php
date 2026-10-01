<?php

declare(strict_types=1);

namespace DevExtreme\Data\Tests\Integration;

use DevExtreme\Data\Contracts\DataSourceInterface;
use DevExtreme\Data\PdoSource;
use DevExtreme\Data\Tests\Support\PdoFixture;
use DevExtreme\Data\Tests\Support\SourceContractTestCase;
use PDO;

/**
 * Runs the shared contract against a real MySQL/MariaDB server.
 * Enabled by DEVEXTREME_TEST_MYSQL_DSN (see phpunit.xml.dist); skipped otherwise.
 */
final class PdoMysqlSourceTest extends SourceContractTestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $dsn = (string) getenv('DEVEXTREME_TEST_MYSQL_DSN');
        if ($dsn === '' || !extension_loaded('pdo_mysql')) {
            self::markTestSkipped('Set DEVEXTREME_TEST_MYSQL_DSN to run MySQL tests.');
        }

        $this->pdo = new PDO(
            $dsn,
            (string) (getenv('DEVEXTREME_TEST_MYSQL_USER') ?: 'root'),
            (string) getenv('DEVEXTREME_TEST_MYSQL_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false],
        );
    }

    protected function createSource(array $rows): DataSourceInterface
    {
        PdoFixture::createOrders($this->pdo, $rows);

        return new PdoSource($this->pdo, 'orders');
    }
}
