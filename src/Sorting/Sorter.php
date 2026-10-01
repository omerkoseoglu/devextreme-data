<?php

declare(strict_types=1);

namespace DevExtreme\Data\Sorting;

use DevExtreme\Data\Grouping\GroupKey;
use DevExtreme\Data\GroupingInfo;
use DevExtreme\Data\SortingInfo;
use DevExtreme\Data\Support\Accessor;
use DevExtreme\Data\Support\Compare;

/**
 * Stable multi-key in-memory sorting. `null` sorts first in ascending order.
 * For grouping levels with a groupInterval the interval key (not the raw value) is compared.
 */
final class Sorter
{
    /**
     * @param list<mixed>       $rows
     * @param list<SortingInfo> $sort
     *
     * @return list<mixed>
     */
    public static function sort(array $rows, array $sort, bool $ignoreCase = true): array
    {
        if ($sort === [] || count($rows) < 2) {
            return $rows;
        }

        $keys = [];
        foreach ($rows as $i => $row) {
            $rowKeys = [];
            foreach ($sort as $info) {
                $value = Accessor::read($row, $info->selector);
                $rowKeys[] = $info instanceof GroupingInfo ? GroupKey::compute($value, $info) : $value;
            }
            $keys[$i] = $rowKeys;
        }

        $order = array_keys($rows);
        usort($order, static function (int $a, int $b) use ($keys, $sort, $ignoreCase): int {
            foreach ($sort as $n => $info) {
                $x = $keys[$a][$n];
                $y = $keys[$b][$n];

                $cmp = is_string($x) && is_string($y) && !is_numeric($x) && !is_numeric($y)
                    ? Compare::compareStringsForSort($x, $y, $ignoreCase)
                    : Compare::compare($x, $y);

                if ($cmp !== 0) {
                    return $info->desc ? -$cmp : $cmp;
                }
            }

            return $a <=> $b;
        });

        return array_map(static fn (int $i): mixed => $rows[$i], $order);
    }
}
