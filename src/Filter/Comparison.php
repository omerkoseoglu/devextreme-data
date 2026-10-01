<?php

declare(strict_types=1);

namespace DevExtreme\Data\Filter;

/**
 * `[field, operation, value]` (or `[field, value]`, meaning "=").
 */
final class Comparison extends Node
{
    public const CONTAINS = 'contains';
    public const NOT_CONTAINS = 'notcontains';
    public const STARTS_WITH = 'startswith';
    public const ENDS_WITH = 'endswith';

    public function __construct(
        public readonly string $field,
        public readonly string $operation,
        public readonly mixed $value,
    ) {
    }

    public function isStringOperation(): bool
    {
        return in_array($this->operation, [self::CONTAINS, self::NOT_CONTAINS, self::STARTS_WITH, self::ENDS_WITH], true);
    }
}
