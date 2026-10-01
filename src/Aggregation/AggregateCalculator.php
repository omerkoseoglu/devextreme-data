<?php

declare(strict_types=1);

namespace DevExtreme\Data\Aggregation;

use DevExtreme\Data\Group;
use DevExtreme\Data\SummaryInfo;

/**
 * Calculates total summaries and (nested) group summaries over rows or groups of rows.
 */
final class AggregateCalculator
{
    /** @var list<Aggregator>|null */
    private ?array $totalAggregators = null;

    /** @var list<list<Aggregator>>|null */
    private ?array $groupStack = null;

    /**
     * @param iterable<mixed>   $data         Rows or {@see Group}s (groups get their `summary` filled in)
     * @param list<SummaryInfo> $totalSummary
     * @param list<SummaryInfo> $groupSummary
     */
    public function __construct(
        private readonly iterable $data,
        private readonly array $totalSummary,
        private readonly array $groupSummary,
    ) {
        if ($totalSummary !== []) {
            $this->totalAggregators = $this->createAggregators($totalSummary);
        }

        if ($groupSummary !== []) {
            $this->groupStack = [];
        }
    }

    /**
     * @return list<mixed>|null total summary values, or null when there is no total summary
     */
    public function run(): ?array
    {
        foreach ($this->data as $item) {
            $this->processItem($item);
        }

        return $this->totalAggregators === null ? null : $this->finish($this->totalAggregators);
    }

    private function processItem(mixed $item): void
    {
        if ($item instanceof Group) {
            $this->processGroup($item);

            return;
        }

        if ($this->groupStack !== null) {
            foreach ($this->groupStack as $aggregators) {
                $this->step($item, $aggregators, $this->groupSummary);
            }
        }

        if ($this->totalAggregators !== null) {
            $this->step($item, $this->totalAggregators, $this->totalSummary);
        }
    }

    private function processGroup(Group $group): void
    {
        if ($this->groupStack !== null) {
            $this->groupStack[] = $this->createAggregators($this->groupSummary);
        }

        foreach ($group->items ?? [] as $child) {
            $this->processItem($child);
        }

        if ($this->groupStack !== null) {
            $group->summary = $this->finish(array_pop($this->groupStack));
        }
    }

    /**
     * @param list<Aggregator>  $aggregators
     * @param list<SummaryInfo> $summary
     */
    private function step(mixed $item, array $aggregators, array $summary): void
    {
        foreach ($aggregators as $i => $aggregator) {
            $aggregator->step($item, $summary[$i]->selector);
        }
    }

    /**
     * @param list<Aggregator> $aggregators
     *
     * @return list<mixed>
     */
    private function finish(array $aggregators): array
    {
        return array_map(static fn (Aggregator $a): mixed => $a->finish(), $aggregators);
    }

    /**
     * @param list<SummaryInfo> $summary
     *
     * @return list<Aggregator>
     */
    private function createAggregators(array $summary): array
    {
        return array_map(
            static fn (SummaryInfo $s): Aggregator => CustomAggregators::create($s->summaryType),
            $summary,
        );
    }
}
