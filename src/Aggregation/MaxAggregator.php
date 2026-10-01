<?php

declare(strict_types=1);

namespace DevExtreme\Data\Aggregation;

use DateTimeInterface;
use DevExtreme\Data\Support\Accessor;
use DevExtreme\Data\Support\Compare;

final class MaxAggregator extends Aggregator
{
    private mixed $max = null;

    public function step(mixed $item, string $selector): void
    {
        $value = Accessor::read($item, $selector);

        if ((is_scalar($value) || $value instanceof DateTimeInterface) && ($this->max === null || Compare::compare($value, $this->max) > 0)) {
            $this->max = $value;
        }
    }

    public function finish(): mixed
    {
        return $this->max;
    }
}
