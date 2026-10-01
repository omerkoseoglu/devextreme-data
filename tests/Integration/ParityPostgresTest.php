<?php

declare(strict_types=1);

namespace DevExtreme\Data\Tests\Integration;

use PDO;

/**
 * SQL-vs-memory parity on a real PostgreSQL server. Enabled by DEVEXTREME_TEST_PGSQL_DSN; skipped otherwise.
 */
final class ParityPostgresTest extends ParityTest
{
    protected static function connect(): PDO
    {
        $dsn = (string) getenv('DEVEXTREME_TEST_PGSQL_DSN');
        if ($dsn === '') {
            self::markTestSkipped('Set DEVEXTREME_TEST_PGSQL_DSN to run PostgreSQL tests.');
        }

        return new PDO(
            $dsn,
            (string) (getenv('DEVEXTREME_TEST_PGSQL_USER') ?: 'postgres'),
            (string) getenv('DEVEXTREME_TEST_PGSQL_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}
