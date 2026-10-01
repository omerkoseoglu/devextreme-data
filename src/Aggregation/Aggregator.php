<?php

declare(strict_types=1);

namespace DevExtreme\Data\Aggregation;

/**
 * Accumulates a summary value over a stream of items. Extend it to build a custom aggregator
 * and register it with {@see CustomAggregators::register()}.
 */
abstract class Aggregator
{
    abstract public function step(mixed $item, string $selector): void;

    abstract public function finish(): mixed;
}
