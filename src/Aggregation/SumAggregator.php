<?php

declare(strict_types=1);

namespace DevExtreme\Data\Aggregation;

use DevExtreme\Data\Support\Accessor;

final class SumAggregator extends Aggregator
{
    private int|float|null $sum = null;

    public function step(mixed $item, string $selector): void
    {
        $value = Accessor::read($item, $selector);

        if (is_bool($value)) {
            $value = (int) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            $value += 0;
        }

        if (is_int($value) || is_float($value)) {
            $this->sum = ($this->sum ?? 0) + $value;
        }
    }

    public function finish(): int|float|null
    {
        return $this->sum;
    }
}
