<?php

declare(strict_types=1);

namespace DevExtreme\Data\Aggregation;

use DevExtreme\Data\Support\Accessor;

final class CountAggregator extends Aggregator
{
    private int $count = 0;

    public function __construct(private readonly bool $skipNulls = false)
    {
    }

    public function step(mixed $item, string $selector): void
    {
        if (!$this->skipNulls || Accessor::read($item, $selector) !== null) {
            ++$this->count;
        }
    }

    public function finish(): int
    {
        return $this->count;
    }
}
