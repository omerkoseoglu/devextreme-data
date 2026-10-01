<?php

declare(strict_types=1);

namespace DevExtreme\Data\Sql;

use DevExtreme\Data\Group;
use DevExtreme\Data\Grouping\GroupKey;
use DevExtreme\Data\GroupingInfo;
use DevExtreme\Data\SummaryInfo;
use DevExtreme\Data\Support\Compare;

/**
 * Rebuilds nested groups, group summaries and total summaries from the flat rows of a single
 * `GROUP BY` query (one row per last-level group).
 *
 * Row layout: [key_0 .. key_n-1, COUNT(*), <total summary cells>, <group summary cells>] where the cells of
 * a summary are: sum => [SUM], min => [MIN], max => [MAX], avg => [SUM, COUNT(col)], count => [].
 *
 * @internal
 */
final class RemoteGroupTransformer
{
    /**
     * Number of result columns a summary occupies after COUNT(*).
     */
    public static function width(SummaryInfo $summary): int
    {
        return match ($summary->summaryType) {
            SummaryInfo::COUNT => 0,
            SummaryInfo::AVG => 2,
            default => 1,
        };
    }

    /**
     * @param list<list<mixed>>  $rows
     * @param list<GroupingInfo> $levels
     * @param list<SummaryInfo>  $totalSummary
     * @param list<SummaryInfo>  $groupSummary
     *
     * @return array{groups: list<Group>, totals: list<mixed>|null, totalCount: int}
     */
    public static function transform(array $rows, array $levels, array $totalSummary, array $groupSummary): array
    {
        $depth = count($levels);
        $totalWidth = array_sum(array_map(self::width(...), $totalSummary));

        /** @var list<array{keys: list<mixed>, count: int, total: list<mixed>, group: list<mixed>}> $leaves */
        $leaves = [];
        foreach ($rows as $row) {
            $row = array_values($row);
            $keys = [];
            for ($i = 0; $i < $depth; ++$i) {
                $keys[] = self::normalizeKey($row[$i], $levels[$i]);
            }

            $cells = array_slice($row, $depth + 1);
            $leaves[] = [
                'keys' => $keys,
                'count' => (int) $row[$depth],
                'total' => array_slice($cells, 0, $totalWidth),
                'group' => array_slice($cells, $totalWidth),
            ];
        }

        return [
            'groups' => $depth > 0 ? self::buildLevel($leaves, 0, $depth, $groupSummary) : [],
            'totals' => $totalSummary === [] ? null : self::rollup($leaves, $totalSummary, 'total'),
            'totalCount' => array_sum(array_column($leaves, 'count')),
        ];
    }

    /**
     * @param list<array{keys: list<mixed>, count: int, total: list<mixed>, group: list<mixed>}> $leaves
     * @param list<SummaryInfo>                                                                   $groupSummary
     *
     * @return list<Group>
     */
    private static function buildLevel(array $leaves, int $level, int $depth, array $groupSummary): array
    {
        /** @var array<string, array{key: mixed, leaves: list<array{keys: list<mixed>, count: int, total: list<mixed>, group: list<mixed>}>}> $buckets */
        $buckets = [];
        foreach ($leaves as $leaf) {
            $id = GroupKey::identity($leaf['keys'][$level]);
            $buckets[$id]['key'] = $leaf['keys'][$level];
            $buckets[$id]['leaves'][] = $leaf;
        }

        $isLast = $level === $depth - 1;
        $groups = [];
        foreach ($buckets as $bucket) {
            $group = new Group($bucket['key']);

            if ($isLast) {
                $group->count = array_sum(array_column($bucket['leaves'], 'count'));
            } else {
                $group->items = self::buildLevel($bucket['leaves'], $level + 1, $depth, $groupSummary);
            }

            if ($groupSummary !== []) {
                $group->summary = self::rollup($bucket['leaves'], $groupSummary, 'group');
            }

            $groups[] = $group;
        }

        return $groups;
    }

    /**
     * @param list<array{keys: list<mixed>, count: int, total: list<mixed>, group: list<mixed>}> $leaves
     * @param list<SummaryInfo>                                                                   $summaries
     *
     * @return list<mixed>
     */
    private static function rollup(array $leaves, array $summaries, string $cells): array
    {
        $result = [];
        $offset = 0;

        foreach ($summaries as $summary) {
            $result[] = match ($summary->summaryType) {
                SummaryInfo::COUNT => array_sum(array_column($leaves, 'count')),
                SummaryInfo::SUM => self::sum($leaves, $cells, $offset),
                SummaryInfo::AVG => self::avg($leaves, $cells, $offset),
                SummaryInfo::MIN => self::extreme($leaves, $cells, $offset, -1),
                default => self::extreme($leaves, $cells, $offset, 1),
            };

            $offset += self::width($summary);
        }

        return $result;
    }

    /**
     * @param list<array{keys: list<mixed>, count: int, total: list<mixed>, group: list<mixed>}> $leaves
     */
    private static function sum(array $leaves, string $cells, int $offset): int|float|null
    {
        $sum = null;
        foreach ($leaves as $leaf) {
            $value = self::number($leaf[$cells][$offset] ?? null);
            if ($value !== null) {
                $sum = ($sum ?? 0) + $value;
            }
        }

        return $sum;
    }

    /**
     * @param list<array{keys: list<mixed>, count: int, total: list<mixed>, group: list<mixed>}> $leaves
     */
    private static function avg(array $leaves, string $cells, int $offset): int|float|null
    {
        $sum = null;
        $count = 0;
        foreach ($leaves as $leaf) {
            $value = self::number($leaf[$cells][$offset] ?? null);
            if ($value !== null) {
                $sum = ($sum ?? 0) + $value;
            }
            $count += (int) ($leaf[$cells][$offset + 1] ?? 0);
        }

        return $sum === null || $count === 0 ? null : $sum / $count;
    }

    /**
     * @param list<array{keys: list<mixed>, count: int, total: list<mixed>, group: list<mixed>}> $leaves
     * @param int                                                                                $direction -1 for min, 1 for max
     */
    private static function extreme(array $leaves, string $cells, int $offset, int $direction): mixed
    {
        $best = null;
        foreach ($leaves as $leaf) {
            $value = $leaf[$cells][$offset] ?? null;
            if ($value !== null && ($best === null || Compare::compare($value, $best) * $direction > 0)) {
                $best = $value;
            }
        }

        return $best;
    }

    private static function number(mixed $value): int|float|null
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return is_string($value) && is_numeric($value) ? $value + 0 : null;
    }

    private static function normalizeKey(mixed $key, GroupingInfo $info): mixed
    {
        if ($info->groupInterval !== null && is_string($key) && is_numeric($key)) {
            return $key + 0;
        }

        return $key;
    }
}
