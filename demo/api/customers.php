<?php

declare(strict_types=1);

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
