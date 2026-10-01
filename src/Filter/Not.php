<?php

declare(strict_types=1);

namespace DevExtreme\Data\Filter;

/**
 * `["!", criteria]`
 */
final class Not extends Node
{
    public function __construct(public readonly Node $operand)
    {
    }
}
