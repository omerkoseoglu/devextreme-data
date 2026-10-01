<?php

declare(strict_types=1);

namespace DevExtreme\Data\Select;

use DevExtreme\Data\Support\Accessor;

/**
 * Projects rows onto a list of (dotted) field names, re-nesting dotted paths:
 * `["id", "customer.name"]` becomes `['id' => .., 'customer' => ['name' => ..]]`.
 */
final class SelectHelper
{
    /**
     * @param iterable<mixed> $rows
     * @param list<string>    $select
     *
     * @return list<array<string, mixed>>
     */
    public static function evaluate(iterable $rows, array $select): array
    {
        $paths = array_map(static fn (string $s): array => explode('.', $s), $select);
        $result = [];

        foreach ($rows as $row) {
            $projected = [];
            foreach ($paths as $i => $path) {
                self::assign($projected, $path, Accessor::read($row, $select[$i]));
            }
            $result[] = $projected;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $target
     * @param list<string>         $path
     */
    private static function assign(array &$target, array $path, mixed $value): void
    {
        $key = array_shift($path);

        if ($path === []) {
            $target[$key] = $value;

            return;
        }

        if (!array_key_exists($key, $target)) {
            $target[$key] = [];
        }

        if (is_array($target[$key])) {
            self::assign($target[$key], $path, $value);
        }
    }
}
