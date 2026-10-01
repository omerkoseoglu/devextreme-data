<?php

declare(strict_types=1);

namespace DevExtreme\Data;

/**
 * Derived, backend-neutral facts about a load request (port of DataSourceLoadContext).
 *
 * @internal
 */
final class LoadContext
{
    public function __construct(
        public readonly LoadOptions $options,
        private readonly bool $stringToLowerDefault,
        private readonly bool $remoteGroupingDefault,
    ) {
    }

    // ---- total count -----------------------------------------------------------------------

    public function requireTotalCount(): bool
    {
        return $this->options->requireTotalCount;
    }

    public function requireGroupCount(): bool
    {
        return $this->options->requireGroupCount;
    }

    public function isCountQuery(): bool
    {
        return $this->options->isCountQuery;
    }

    public function isSummaryQuery(): bool
    {
        return $this->options->isSummaryQuery;
    }

    // ---- paging ----------------------------------------------------------------------------

    public function skip(): int
    {
        return max(0, $this->options->skip);
    }

    public function take(): int
    {
        return max(0, $this->options->take);
    }

    public function hasPaging(): bool
    {
        return $this->skip() > 0 || $this->take() > 0;
    }

    public function paginateViaPrimaryKey(): bool
    {
        return $this->options->paginateViaPrimaryKey ?? false;
    }

    // ---- filter ----------------------------------------------------------------------------

    public function hasFilter(): bool
    {
        return $this->options->filter !== null && $this->options->filter !== [];
    }

    public function useStringToLower(): bool
    {
        return $this->options->stringToLower ?? $this->stringToLowerDefault;
    }

    // ---- grouping --------------------------------------------------------------------------

    /**
     * @return list<GroupingInfo>
     */
    public function groups(): array
    {
        return $this->options->group;
    }

    public function hasGroups(): bool
    {
        return !$this->isSummaryQuery() && $this->options->group !== [];
    }

    public function shouldEmptyGroups(): bool
    {
        return $this->hasGroups() && !$this->options->group[array_key_last($this->options->group)]->getIsExpanded();
    }

    public function useRemoteGrouping(): bool
    {
        return $this->options->remoteGrouping ?? $this->remoteGroupingDefault;
    }

    // ---- sorting & primary key -------------------------------------------------------------

    public function hasSort(): bool
    {
        return $this->options->sort !== [];
    }

    /**
     * @return list<string>
     */
    public function primaryKey(): array
    {
        return $this->options->primaryKey;
    }

    public function hasPrimaryKey(): bool
    {
        return $this->options->primaryKey !== [];
    }

    public function defaultSort(): ?string
    {
        $sort = $this->options->defaultSort;

        return $sort === null || $sort === '' ? null : $sort;
    }

    public function shouldSortByPrimaryKey(): bool
    {
        return $this->hasPrimaryKey() && ($this->options->sortByPrimaryKey ?? true);
    }

    public function hasAnySort(): bool
    {
        return $this->hasGroups() || $this->hasSort() || $this->shouldSortByPrimaryKey() || $this->defaultSort() !== null;
    }

    /**
     * Group levels first, then user sort, then the required default/primary-key sort.
     *
     * @return list<SortingInfo>
     */
    public function fullSort(): array
    {
        $memo = [];
        $result = [];

        $add = static function (SortingInfo $info) use (&$memo, &$result): void {
            $id = strtolower($info->selector);
            if (!isset($memo[$id])) {
                $memo[$id] = true;
                $result[] = $info;
            }
        };

        if ($this->hasGroups()) {
            foreach ($this->options->group as $group) {
                $add($group);
            }
        }

        foreach ($this->options->sort as $sort) {
            $add($sort);
        }

        $required = [];
        if ($this->defaultSort() !== null) {
            $required[] = $this->defaultSort();
        }
        if ($this->shouldSortByPrimaryKey()) {
            array_push($required, ...$this->primaryKey());
        }

        $desc = $result !== [] && $result[array_key_last($result)]->desc;
        foreach ($required as $selector) {
            $add(new SortingInfo($selector, $desc));
        }

        return $result;
    }

    // ---- summary ---------------------------------------------------------------------------

    /**
     * @return list<SummaryInfo>
     */
    public function totalSummary(): array
    {
        return $this->options->totalSummary;
    }

    /**
     * @return list<SummaryInfo>
     */
    public function groupSummary(): array
    {
        return $this->options->groupSummary;
    }

    public function hasTotalSummary(): bool
    {
        return $this->options->totalSummary !== [];
    }

    public function hasGroupSummary(): bool
    {
        return $this->hasGroups() && $this->options->groupSummary !== [];
    }

    public function hasSummary(): bool
    {
        return $this->hasTotalSummary() || $this->hasGroupSummary();
    }

    /**
     * True when the only summaries are totals of type "count", so the total count answers them.
     */
    public function summaryIsTotalCountOnly(): bool
    {
        if ($this->hasGroupSummary() || !$this->hasTotalSummary()) {
            return false;
        }

        foreach ($this->options->totalSummary as $summary) {
            if ($summary->summaryType !== SummaryInfo::COUNT) {
                return false;
            }
        }

        return true;
    }

    public function isRemoteTotalSummary(): bool
    {
        return $this->useRemoteGrouping() && !$this->summaryIsTotalCountOnly() && $this->hasSummary() && !$this->hasGroups();
    }

    // ---- select ----------------------------------------------------------------------------

    /**
     * @return list<string>
     */
    public function fullSelect(): array
    {
        $select = $this->options->select;
        $preSelect = $this->options->preSelect;

        if ($preSelect !== [] && $select !== []) {
            $allowed = array_map('strtolower', $select);

            return array_values(array_unique(array_filter(
                $preSelect,
                static fn (string $field): bool => in_array(strtolower($field), $allowed, true),
            )));
        }

        return $preSelect !== [] ? $preSelect : $select;
    }

    public function hasAnySelect(): bool
    {
        return $this->fullSelect() !== [];
    }

    public function useRemoteSelect(): bool
    {
        return $this->options->remoteSelect ?? true;
    }
}
