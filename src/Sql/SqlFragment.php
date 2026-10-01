<?php

declare(strict_types=1);

namespace DevExtreme\Data\Sql;

/**
 * A piece of SQL with positional (`?`) bindings.
 */
final class SqlFragment
{
    /**
     * @param list<mixed> $params
     */
    public function __construct(
        public readonly string $sql,
        public readonly array $params = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->sql === '';
    }

    /**
     * Joins fragments with AND, wrapping each in parentheses. Empty fragments are skipped.
     */
    public static function all(self ...$fragments): self
    {
        $sql = [];
        $params = [];

        foreach ($fragments as $fragment) {
            if ($fragment->isEmpty()) {
                continue;
            }

            $sql[] = '(' . $fragment->sql . ')';
            array_push($params, ...$fragment->params);
        }

        return new self(implode(' AND ', $sql), $params);
    }
}
