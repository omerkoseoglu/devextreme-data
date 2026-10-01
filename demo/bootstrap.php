<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

const DEMO_DB_FILE = __DIR__ . '/data/demo.sqlite';

function demo_pdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $fresh = !is_file(DEMO_DB_FILE);
    $pdo = new PDO('sqlite:' . DEMO_DB_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');

    if ($fresh) {
        demo_seed($pdo);
    }

    return $pdo;
}

function demo_seed(PDO $pdo, int $orders = 3000): void
{
    $pdo->exec('
        CREATE TABLE customers (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            city TEXT NOT NULL,
            country TEXT NOT NULL
        );
        CREATE TABLE orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL REFERENCES customers (id),
            customer TEXT NOT NULL,
            country TEXT NOT NULL,
            product TEXT NOT NULL,
            category TEXT NOT NULL,
            amount REAL NOT NULL,
            qty INTEGER NOT NULL,
            order_date TEXT NOT NULL,
            shipped INTEGER NOT NULL DEFAULT 0,
            note TEXT
        );
        CREATE INDEX idx_orders_date ON orders (order_date);
        CREATE INDEX idx_orders_category ON orders (category);
    ');

    // deterministic pseudo-random data: same database on every machine
    mt_srand(20260101);
    $pick = static fn (array $list): mixed => $list[mt_rand(0, count($list) - 1)];

    $locations = [
        ['Berlin', 'Germany'], ['Munich', 'Germany'], ['Paris', 'France'], ['Lyon', 'France'],
        ['Istanbul', 'Turkey'], ['Ankara', 'Turkey'], ['London', 'United Kingdom'], ['Madrid', 'Spain'],
        ['Rome', 'Italy'], ['Amsterdam', 'Netherlands'],
    ];
    $first = ['Anna', 'Mehmet', 'Sophie', 'Lukas', 'Elif', 'Carlos', 'Giulia', 'Jan', 'Zeynep', 'Oliver', 'Marie', 'Ahmet'];
    $last = ['Schmidt', 'Yilmaz', 'Dubois', 'Garcia', 'Rossi', 'de Vries', 'Smith', 'Kaya', 'Müller', 'Martin'];

    $insertCustomer = $pdo->prepare('INSERT INTO customers (id, name, city, country) VALUES (?, ?, ?, ?)');
    $customers = [];
    $pdo->beginTransaction();
    for ($id = 1; $id <= 60; ++$id) {
        [$city, $country] = $pick($locations);
        $name = $pick($first) . ' ' . $pick($last);
        $insertCustomer->execute([$id, $name, $city, $country]);
        $customers[$id] = ['name' => $name, 'country' => $country];
    }

    $catalog = [
        'Books' => [['Clean Code', 35], ['PHP Internals', 48], ['Refactoring', 42], ['SQL Antipatterns', 39]],
        'Games' => [['Chess Set', 59], ['Puzzle 1000', 19], ['Board Game', 44], ['Card Deck', 9]],
        'Music' => [['Vinyl Record', 27], ['Headphones', 129], ['Guitar Strings', 12], ['Metronome', 24]],
        'Office' => [['Notebook', 6], ['Desk Lamp', 38], ['Monitor Stand', 52], ['Pen Set', 15]],
    ];
    $notes = [null, null, null, 'gift', 'rush delivery', 'Gift card', 'call before delivery'];

    $insertOrder = $pdo->prepare('
        INSERT INTO orders (customer_id, customer, country, product, category, amount, qty, order_date, shipped, note)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $start = strtotime('2023-01-01');
    $span = strtotime('2025-12-31') - $start;
    for ($i = 0; $i < $orders; ++$i) {
        $customerId = mt_rand(1, 60);
        $category = $pick(array_keys($catalog));
        [$product, $price] = $pick($catalog[$category]);
        $qty = mt_rand(1, 6);
        $date = date('Y-m-d H:i:s', $start + mt_rand(0, $span));

        $insertOrder->execute([
            $customerId,
            $customers[$customerId]['name'],
            $customers[$customerId]['country'],
            $product,
            $category,
            round($price * $qty * (mt_rand(90, 110) / 100), 2),
            $qty,
            $date,
            (int) (strtotime($date) < strtotime('2025-10-01')),
            $pick($notes),
        ]);
    }
    $pdo->commit();
}

function demo_json(mixed $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Form-encoded body of PUT/DELETE requests (PHP only fills $_POST for POST).
 *
 * @return array<string, mixed>
 */
function demo_body(): array
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return $_POST;
    }

    parse_str((string) file_get_contents('php://input'), $body);

    return $body;
}
