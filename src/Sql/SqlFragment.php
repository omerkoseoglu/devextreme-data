<?php

declare(strict_types=1);

namespace DevExtreme\Data\Sql;

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
