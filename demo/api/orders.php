<?php

declare(strict_types=1);

/**
 * REST endpoint for DevExpress.data.AspNet.createStore():
 *   GET    -> load (filter / sort / group / summary / paging executed in SQL by PdoSource)
 *   POST   -> insert  (form field "values" = JSON)
 *   PUT    -> update  (form fields "key" and "values")
 *   DELETE -> remove  (form field "key")
 */

use DevExtreme\Data\DataSourceLoader;
use DevExtreme\Data\PdoSource;

require_once __DIR__ . '/../bootstrap.php';

$pdo = demo_pdo();

/** Fields a client is allowed to read, filter, sort, group and summarise. */
const ORDER_COLUMNS = [
    'id' => 'id',
    'customer_id' => 'customer_id',
    'customer' => 'customer',
    'country' => 'country',
    'product' => 'product',
    'category' => 'category',
    'amount' => 'amount',
    'qty' => 'qty',
    'order_date' => 'order_date',
    'shipped' => 'shipped',
    'note' => 'note',
];

/** Fields a client is allowed to write. */
const ORDER_WRITABLE = ['customer_id', 'product', 'category', 'amount', 'qty', 'order_date', 'shipped', 'note'];

try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $source = new PdoSource($pdo, 'orders', columns: ORDER_COLUMNS, primaryKey: ['id']);
        demo_json(DataSourceLoader::loadFromRequest($source));
    }

    if ($method === 'POST') {
        $values = orderValues(demo_body());
        $customer = customerFor($pdo, $values['customer_id'] ?? null);
        $values['customer'] = $customer['name'];
        $values['country'] = $customer['country'];

        $columns = array_keys($values);
        $stmt = $pdo->prepare(sprintf(
            'INSERT INTO orders (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', array_fill(0, count($columns), '?')),
        ));
        $stmt->execute(array_values($values));
        demo_json(['id' => (int) $pdo->lastInsertId()] + $values, 201);
    }

    if ($method === 'PUT') {
        $body = demo_body();
        $values = orderValues($body);
        if (isset($values['customer_id'])) {
            $customer = customerFor($pdo, $values['customer_id']);
            $values['customer'] = $customer['name'];
            $values['country'] = $customer['country'];
        }

        if ($values !== []) {
            $assignments = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', array_keys($values)));
            $stmt = $pdo->prepare("UPDATE orders SET $assignments WHERE id = ?");
            $stmt->execute([...array_values($values), (int) ($body['key'] ?? 0)]);
        }
        demo_json(['ok' => true]);
    }

    if ($method === 'DELETE') {
        $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([(int) (demo_body()['key'] ?? 0)]);
        demo_json(['ok' => true]);
    }

    demo_json(['error' => 'Method not allowed'], 405);
} catch (InvalidArgumentException $e) {
    demo_json(['error' => $e->getMessage()], 400);
}

/**
 * @param array<string, mixed> $body
 *
 * @return array<string, mixed>
 */
function orderValues(array $body): array
{
    $decoded = json_decode((string) ($body['values'] ?? '{}'), true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('"values" must be a JSON object.');
    }

    $values = array_intersect_key($decoded, array_flip(ORDER_WRITABLE));

    if (isset($values['shipped'])) {
        $values['shipped'] = (int) filter_var($values['shipped'], FILTER_VALIDATE_BOOLEAN);
    }

    if (isset($values['order_date'])) {
        $time = strtotime((string) $values['order_date']);
        if ($time === false) {
            throw new InvalidArgumentException('Invalid order_date.');
        }
        $values['order_date'] = date('Y-m-d H:i:s', $time);
    }

    return $values;
}

/**
 * @return array{name: string, country: string}
 */
function customerFor(PDO $pdo, mixed $id): array
{
    $stmt = $pdo->prepare('SELECT name, country FROM customers WHERE id = ?');
    $stmt->execute([(int) $id]);
    $row = $stmt->fetch();

    if ($row === false) {
        throw new InvalidArgumentException('Unknown customer_id.');
    }

    return $row;
}
