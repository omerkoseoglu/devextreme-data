<?php

declare(strict_types=1);

namespace DevExtreme\Data\Filter;

/**
 * A group of operands combined with "and" or "or".
 */
final class Logical extends Node
{
    /**
     * @param list<Node> $operands
     */
    public function __construct(
        public readonly array $operands,
        public readonly bool $isAnd,
    ) {
    }
}
