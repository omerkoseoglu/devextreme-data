<?php

declare(strict_types=1);

namespace DevExtreme\Data\Aggregation;

use DateTimeInterface;
use DevExtreme\Data\Support\Accessor;
use DevExtreme\Data\Support\Compare;

final class MinAggregator extends Aggregator
{
    private mixed $min = null;

    public function step(mixed $item, string $selector): void
    {
        $value = Accessor::read($item, $selector);

        if ((is_scalar($value) || $value instanceof DateTimeInterface) && ($this->min === null || Compare::compare($value, $this->min) < 0)) {
            $this->min = $value;
        }
    }

    public function finish(): mixed
    {
        return $this->min;
    }
}
