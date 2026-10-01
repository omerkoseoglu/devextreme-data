<?php

declare(strict_types=1);

namespace DevExtreme\Data\Tests\Support;

use PDO;

/**
 * Creates and fills the `orders` table on a PDO connection.
 */
final class PdoFixture
{
    public static function sqlite(): PDO
    {
        if (!extension_loaded('pdo_sqlite')) {
            throw new \PHPUnit\Framework\SkippedWithMessageException('pdo_sqlite is not available');
        }

        return new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public static function createOrders(PDO $pdo, array $rows): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $pdo->exec('DROP TABLE IF EXISTS orders');

        $ddl = match ($driver) {
            'sqlite' => 'CREATE TABLE orders (id INTEGER PRIMARY KEY, customer TEXT, category TEXT, amount REAL, qty INTEGER, ordered_at TEXT, shipped INTEGER, note TEXT)',
            'mysql' => 'CREATE TABLE orders (id INT PRIMARY KEY, customer VARCHAR(50), category VARCHAR(50), amount DOUBLE, qty INT, ordered_at DATETIME, shipped INT, note VARCHAR(100)) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            default => 'CREATE TABLE orders (id INT PRIMARY KEY, customer VARCHAR(50), category VARCHAR(50), amount DOUBLE PRECISION, qty INT, ordered_at TIMESTAMP, shipped INT, note VARCHAR(100))',
        };
        $pdo->exec($ddl);

        $insert = $pdo->prepare('INSERT INTO orders (id, customer, category, amount, qty, ordered_at, shipped, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($rows as $row) {
            $insert->execute(array_values($row));
        }
    }
}
