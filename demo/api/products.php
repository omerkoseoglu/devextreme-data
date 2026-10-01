<?php

declare(strict_types=1);

/**
 * In-memory example: the same loader over a plain PHP array (no database at all).
 */

use DevExtreme\Data\ArraySource;
use DevExtreme\Data\DataSourceLoader;

require_once __DIR__ . '/../bootstrap.php';

/** @return list<array<string, mixed>> */
function demo_products(): array
{
    $categories = ['Books', 'Games', 'Music', 'Office', 'Garden'];
    $adjectives = ['Classic', 'Modern', 'Compact', 'Deluxe', 'Essential', 'Portable', 'Vintage', 'Pro'];
    $nouns = ['Lamp', 'Notebook', 'Speaker', 'Chair', 'Planner', 'Kettle', 'Backpack', 'Keyboard', 'Puzzle', 'Plant Pot'];

    mt_srand(42);
    $products = [];
    for ($id = 1; $id <= 200; ++$id) {
        $products[] = [
            'id' => $id,
            'name' => $adjectives[$id % count($adjectives)] . ' ' . $nouns[intdiv($id, 3) % count($nouns)] . ' ' . $id,
            'category' => $categories[$id % count($categories)],
            'price' => round(mt_rand(300, 25000) / 100, 2),
            'stock' => mt_rand(0, 120),
            'released' => date('Y-m-d', strtotime('2022-01-01') + mt_rand(0, 4 * 365) * 86400),
            'discontinued' => mt_rand(0, 9) === 0,
        ];
    }

    return $products;
}

try {
    demo_json(DataSourceLoader::loadFromRequest(new ArraySource(demo_products())));
} catch (InvalidArgumentException $e) {
    demo_json(['error' => $e->getMessage()], 400);
}
