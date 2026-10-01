<?php

declare(strict_types=1);

namespace DevExtreme\Data;

use InvalidArgumentException;

class SortingInfo
{
    public function __construct(
        public string $selector,
        public bool $desc = false,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['selector']) || !is_string($data['selector'])) {
            throw new InvalidArgumentException('A sort/group item requires a string "selector".');
        }

        return new self($data['selector'], self::toBool($data['desc'] ?? false));
    }

    protected static function toBool(mixed $value): bool
    {
        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) $value;
    }
}
