<?php

declare(strict_types=1);

namespace DevExtreme\Data;

use DevExtreme\Data\Aggregation\AggregateCalculator;
use DevExtreme\Data\Contracts\DataSourceInterface;
use DevExtreme\Data\Filter\FilterParser;
use DevExtreme\Data\Filter\MemoryFilterCompiler;
use DevExtreme\Data\Select\SelectHelper;
use DevExtreme\Data\Sorting\Sorter;

final class ArraySource implements DataSourceInterface
{
    /**
     * @param iterable<mixed> $items rows as associative arrays, objects, ArrayAccess objects...
     */
    public function __construct(private readonly iterable $items)
    {
    }

    public function load(LoadOptions $options): LoadResult
    {
        $ctx = new LoadContext($options, stringToLowerDefault: true, remoteGroupingDefault: false);

        $rows = is_array($this->items) ? array_values($this->items) : iterator_to_array($this->items, false);

        if ($ctx->hasFilter()) {
            /** @var non-empty-list<mixed> $filter */
            $filter = $options->filter;
            $predicate = (new MemoryFilterCompiler($ctx->useStringToLower()))->compile(FilterParser::parse($filter));
            $rows = array_values(array_filter($rows, $predicate));
        }

        $total = count($rows);

        if ($ctx->isCountQuery()) {
            return new LoadResult(totalCount: $total);
        }

        if ($ctx->isSummaryQuery()) {
            return $this->loadAggregatesOnly($rows, $ctx);
        }

        if ($ctx->hasAnySort()) {
            $rows = Sorter::sort($rows, $ctx->fullSort());
        }

        $deferPaging = $ctx->hasGroups() || (!$ctx->summaryIsTotalCountOnly() && $ctx->hasSummary());

        if (!$deferPaging) {
            $rows = Pipeline::paginate($rows, $ctx->skip(), $ctx->take());
        }

        if ($ctx->hasAnySelect()) {
            $rows = SelectHelper::evaluate($rows, $ctx->fullSelect());
        }

        return Pipeline::assemble($rows, $ctx, static fn (): int => $total, new LoadResult(), $deferPaging);
    }

    /**
     * @param list<mixed> $rows
     */
    private function loadAggregatesOnly(array $rows, LoadContext $ctx): LoadResult
    {
        $result = new LoadResult();

        if ($ctx->hasTotalSummary()) {
            $result->summary = $ctx->summaryIsTotalCountOnly()
                ? array_fill(0, count($ctx->totalSummary()), count($rows))
                : (new AggregateCalculator($rows, $ctx->totalSummary(), []))->run();
        }

        return $result;
    }
}
