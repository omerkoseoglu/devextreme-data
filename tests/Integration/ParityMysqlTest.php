<?php

declare(strict_types=1);

namespace DevExtreme\Data\Tests\Integration;

use PDO;

/**
 * SQL-vs-memory parity on a real MySQL server. Enabled by DEVEXTREME_TEST_MYSQL_DSN; skipped otherwise.
 */
final class ParityMysqlTest extends ParityTest
{
    protected static function connect(): PDO
    {
        $dsn = (string) getenv('DEVEXTREME_TEST_MYSQL_DSN');
        if ($dsn === '') {
            self::markTestSkipped('Set DEVEXTREME_TEST_MYSQL_DSN to run MySQL tests.');
        }

        return new PDO(
            $dsn,
            (string) (getenv('DEVEXTREME_TEST_MYSQL_USER') ?: 'root'),
            (string) getenv('DEVEXTREME_TEST_MYSQL_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}
