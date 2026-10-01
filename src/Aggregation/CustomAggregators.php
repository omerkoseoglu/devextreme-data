<?php

declare(strict_types=1);

namespace DevExtreme\Data\Aggregation;

use InvalidArgumentException;

/**
 * Registry of custom summary types (counterpart of `CustomAggregators.RegisterAggregator`).
 *
 * Custom aggregators run in PHP, so SQL sources fall back to loading rows for queries that use them.
 */
final class CustomAggregators
{
    /** @var array<string, callable(): Aggregator> */
    private static array $factories = [];

    /**
     * @param callable(): Aggregator $factory creates a fresh aggregator instance per group/total
     */
    public static function register(string $summaryType, callable $factory): void
    {
        if (in_array($summaryType, ['sum', 'min', 'max', 'avg', 'count'], true)) {
            throw new InvalidArgumentException(sprintf('"%s" is a built-in summary type.', $summaryType));
        }

        self::$factories[$summaryType] = $factory;
    }

    public static function clear(): void
    {
        self::$factories = [];
    }

    public static function create(string $summaryType): Aggregator
    {
        return match ($summaryType) {
            'sum' => new SumAggregator(),
            'min' => new MinAggregator(),
            'max' => new MaxAggregator(),
            'avg' => new AvgAggregator(),
            'count' => new CountAggregator(),
            default => isset(self::$factories[$summaryType])
                ? (self::$factories[$summaryType])()
                : throw new InvalidArgumentException(sprintf('Unsupported summary type "%s".', $summaryType)),
        };
    }
}
