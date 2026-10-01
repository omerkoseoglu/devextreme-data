<?php

declare(strict_types=1);

namespace DevExtreme\Data;

use DevExtreme\Data\Aggregation\AggregateCalculator;
use DevExtreme\Data\Grouping\Grouper;

/**
 * Backend independent post-processing steps shared by the array and SQL sources.
 *
 * @internal
 */
final class Pipeline
{
    /**
     * Applies skip/take.
     *
     * @param list<mixed> $data
     *
     * @return list<mixed>
     */
    public static function paginate(array $data, int $skip, int $take): array
    {
        if ($skip < 1 && $take < 1) {
            return $data;
        }

        return array_slice($data, max(0, $skip), $take > 0 ? $take : null);
    }

    /**
     * Groups, aggregates and pages already filtered, sorted (and possibly selected) rows in memory.
     *
     * @param list<mixed> $rows
     * @param callable(): int $totalCount lazily returns the filtered row count (before paging)
     */
    public static function assemble(array $rows, LoadContext $ctx, callable $totalCount, LoadResult $result, bool $deferPaging): LoadResult
    {
        $data = $rows;

        if ($ctx->hasGroups()) {
            $data = Grouper::group($rows, $ctx->groups());

            if ($ctx->requireGroupCount()) {
                $result->groupCount = count($data);
            }
        }

        $count = -1;
        if ($ctx->requireTotalCount() || $ctx->summaryIsTotalCountOnly()) {
            $count = $totalCount();
        }

        if ($ctx->requireTotalCount()) {
            $result->totalCount = $count;
        }

        if ($ctx->summaryIsTotalCountOnly()) {
            $result->summary = array_fill(0, count($ctx->totalSummary()), $count);
        } elseif ($ctx->hasSummary()) {
            $result->summary = (new AggregateCalculator($data, $ctx->totalSummary(), $ctx->groupSummary()))->run();
        }

        if ($deferPaging) {
            $data = self::paginate($data, $ctx->skip(), $ctx->take());
        }

        if ($ctx->shouldEmptyGroups()) {
            self::emptyGroups($data, count($ctx->groups()));
        }

        $result->data = $data;

        return $result;
    }

    /**
     * Replaces the items of last-level groups with their count.
     *
     * @param list<mixed> $groups
     */
    public static function emptyGroups(array $groups, int $level): void
    {
        foreach ($groups as $group) {
            if (!$group instanceof Group) {
                continue;
            }

            if ($level < 2) {
                $group->count = count($group->items ?? []);
                $group->items = null;
            } else {
                self::emptyGroups($group->items ?? [], $level - 1);
            }
        }
    }
}
