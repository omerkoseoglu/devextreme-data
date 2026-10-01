<?php

declare(strict_types=1);

namespace DevExtreme\Data\Filter;

use InvalidArgumentException;

/**
 * Parses the nested-array filter syntax used by DevExtreme:
 *
 *  - `["field", "=", value]`, `["field", value]`
 *  - `[criteria, "and", criteria, "or", ...]` (and/or cannot be mixed inside one group)
 *  - `["!", criteria]`
 */
final class FilterParser
{
    /**
     * @param array<mixed> $criteria
     *
     * @throws InvalidArgumentException
     */
    public static function parse(array $criteria): Node
    {
        return self::parseCore($criteria);
    }

    /**
     * @param array<mixed> $criteria
     */
    private static function parseCore(array $criteria): Node
    {
        if ($criteria === [] || !array_is_list($criteria)) {
            throw new InvalidArgumentException('A filter expression must be a non-empty array.');
        }

        if (is_array($criteria[0])) {
            return self::parseGroup($criteria);
        }

        if (self::isUnary($criteria)) {
            return self::parseUnary($criteria);
        }

        return self::parseBinary($criteria);
    }

    /**
     * @param array<mixed> $criteria
     */
    private static function parseBinary(array $criteria): Node
    {
        if (count($criteria) < 2) {
            throw new InvalidArgumentException('A filter condition requires at least a field and a value.');
        }

        $hasExplicitOperation = count($criteria) > 2;

        return new Comparison(
            self::scalarToString($criteria[0]),
            $hasExplicitOperation ? strtolower(self::scalarToString($criteria[1])) : '=',
            $criteria[$hasExplicitOperation ? 2 : 1],
        );
    }

    /**
     * @param array<mixed> $criteria
     */
    private static function parseUnary(array $criteria): Node
    {
        if (!isset($criteria[1]) || !is_array($criteria[1])) {
            throw new InvalidArgumentException('The "!" operator requires a filter expression.');
        }

        return new Not(self::parseCore($criteria[1]));
    }

    /**
     * @param array<mixed> $criteria
     */
    private static function parseGroup(array $criteria): Node
    {
        /** @var list<Node> $operands */
        $operands = [];
        $isAnd = true;
        $nextIsAnd = true;

        foreach ($criteria as $item) {
            if (is_array($item)) {
                if (count($operands) > 1 && $isAnd !== $nextIsAnd) {
                    throw new InvalidArgumentException('Mixing of and/or is not allowed inside a single group.');
                }

                $isAnd = $nextIsAnd;
                $operands[] = self::parseCore($item);
                $nextIsAnd = true;
            } else {
                $nextIsAnd = preg_match('/and|&/i', self::scalarToString($item)) === 1;
            }
        }

        if (count($operands) === 1) {
            return $operands[0];
        }

        return new Logical($operands, $isAnd);
    }

    /**
     * @param array<mixed> $criteria
     */
    private static function isUnary(array $criteria): bool
    {
        return is_scalar($criteria[0]) && (string) $criteria[0] === '!';
    }

    private static function scalarToString(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        throw new InvalidArgumentException('Unexpected non-scalar value in a filter expression.');
    }
}
