<?php

declare(strict_types=1);

namespace DevExtreme\Data\Filter;

use DevExtreme\Data\Sql\ColumnResolver;

/**
 * Describes a binary filter condition to a custom compiler registered in {@see CustomFilterCompilers}.
 */
final class BinaryExpressionInfo
{
    public const TARGET_MEMORY = 'memory';
    public const TARGET_SQL = 'sql';

    public function __construct(
        /** "memory" or "sql": the kind of result the backend expects from the compiler. */
        public readonly string $target,
        public readonly string $field,
        /** Lower-cased operation, e.g. "=", "contains", or a custom one. */
        public readonly string $operation,
        public readonly mixed $value,
        public readonly bool $stringToLower,
        /** SQL target only: resolves a field name to a safe SQL expression. */
        public readonly ?ColumnResolver $columns = null,
    ) {
    }
}
