<?php

declare(strict_types=1);

/**
 * Read-only lookup endpoint (used by the grid's "Customer" lookup editor).
 */

use DevExtreme\Data\DataSourceLoader;
use DevExtreme\Data\PdoSource;

require_once __DIR__ . '/../bootstrap.php';

try {
    demo_json(DataSourceLoader::loadFromRequest(
        new PdoSource(demo_pdo(), 'customers', primaryKey: ['id']),
    ));
} catch (InvalidArgumentException $e) {
    demo_json(['error' => $e->getMessage()], 400);
}
