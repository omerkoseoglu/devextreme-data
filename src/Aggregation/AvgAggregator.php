<?php

declare(strict_types=1);

namespace DevExtreme\Data\Aggregation;

final class AvgAggregator extends Aggregator
{
    private readonly CountAggregator $counter;
    private readonly SumAggregator $summator;

    public function __construct()
    {
        $this->counter = new CountAggregator(true);
        $this->summator = new SumAggregator();
    }

    public function step(mixed $item, string $selector): void
    {
        $this->counter->step($item, $selector);
        $this->summator->step($item, $selector);
    }

    public function finish(): int|float|null
    {
        $count = $this->counter->finish();
        $sum = $this->summator->finish();

        if ($count === 0 || $sum === null) {
            return null;
        }

        return $sum / $count;
    }
}
