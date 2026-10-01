<?php

declare(strict_types=1);

namespace DevExtreme\Data\Sql;

use DateTimeInterface;
use DevExtreme\Data\Filter\BinaryExpressionInfo;
use DevExtreme\Data\Filter\Comparison;
use DevExtreme\Data\Filter\CustomFilterCompilers;
use DevExtreme\Data\Filter\Logical;
use DevExtreme\Data\Filter\Node;
use DevExtreme\Data\Filter\Not;
use DevExtreme\Data\Support\Str;
use InvalidArgumentException;

/**
 * Compiles a filter {@see Node} tree into a parameterised SQL condition.
 */
final class SqlFilterCompiler
{
    private const ISO_DATE_TIME = '/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}(?::\d{2})?)(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?$/';

    public function __construct(
        private readonly Dialect $dialect,
        private readonly ColumnResolver $columns,
        private readonly bool $stringToLower,
        private readonly bool $normalizeDates = true,
    ) {
    }

    public function compile(Node $node): SqlFragment
    {
        if ($node instanceof Logical) {
            $parts = [];
            $params = [];

            foreach ($node->operands as $operand) {
                $fragment = $this->compile($operand);
                $parts[] = $fragment->sql;
                array_push($params, ...$fragment->params);
            }

            if ($parts === []) {
                return new SqlFragment($node->isAnd ? '1 = 1' : '1 = 0');
            }

            return new SqlFragment('(' . implode($node->isAnd ? ' AND ' : ' OR ', $parts) . ')', $params);
        }

        if ($node instanceof Not) {
            $inner = $this->compile($node->operand);

            return new SqlFragment('NOT (' . $inner->sql . ')', $inner->params);
        }

        if ($node instanceof Comparison) {
            return $this->compileComparison($node);
        }

        throw new InvalidArgumentException('Unknown filter node.');
    }

    private function compileComparison(Comparison $c): SqlFragment
    {
        if (CustomFilterCompilers::hasCompilers()) {
            $custom = CustomFilterCompilers::tryCompileForSql(new BinaryExpressionInfo(
                BinaryExpressionInfo::TARGET_SQL,
                $c->field,
                $c->operation,
                $c->value,
                $this->stringToLower,
                $this->columns,
            ));

            if ($custom !== null) {
                return $custom;
            }
        }

        $column = $this->columns->resolve($c->field);

        if ($c->isStringOperation()) {
            return $this->compileStringOperation($column, $c);
        }

        $operator = match ($c->operation) {
            '=', '<>', '>', '>=', '<', '<=' => $c->operation,
            default => throw new InvalidArgumentException(sprintf('Unsupported filter operation "%s".', $c->operation)),
        };

        $value = $this->normalizeValue($c->value);

        if ($value === null) {
            return match ($operator) {
                '=' => new SqlFragment($column . ' IS NULL'),
                '<>' => new SqlFragment($column . ' IS NOT NULL'),
                default => new SqlFragment('1 = 0'),
            };
        }

        $expression = $column;
        if ($this->stringToLower && is_string($value)) {
            $expression = $this->dialect->lower($column);
            $value = Str::lower($value);
        }

        // Two-valued logic: a NULL column never satisfies a condition and is always matched by "<>",
        // so NOT(...) behaves exactly like the in-memory source (and like LINQ-to-SQL).
        if ($operator === '<>') {
            return new SqlFragment(sprintf('(%s IS NULL OR %s <> ?)', $column, $expression), [$value]);
        }

        return new SqlFragment(sprintf('(%s IS NOT NULL AND %s %s ?)', $column, $expression, $operator), [$value]);
    }

    private function compileStringOperation(string $column, Comparison $c): SqlFragment
    {
        $needle = Str::from($c->value);
        if ($this->stringToLower) {
            $needle = Str::lower($needle);
        }

        $escape = $this->dialect->likeEscape();
        $escaped = str_replace([$escape, '%', '_'], [$escape . $escape, $escape . '%', $escape . '_'], $needle);

        $pattern = match ($c->operation) {
            Comparison::STARTS_WITH => $escaped . '%',
            Comparison::ENDS_WITH => '%' . $escaped,
            default => '%' . $escaped . '%',
        };

        $text = sprintf('COALESCE(%s, \'\')', $this->dialect->castToText($column));
        if ($this->stringToLower) {
            $text = $this->dialect->lower($text);
        }

        $like = sprintf("%s %s ? ESCAPE '%s'", $text, $this->dialect->likeOperator(), $escape);

        return new SqlFragment($c->operation === Comparison::NOT_CONTAINS ? 'NOT (' . $like . ')' : $like, [$pattern]);
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return (int) $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value)) {
            if ($this->normalizeDates && preg_match(self::ISO_DATE_TIME, $value, $m) === 1) {
                return $m[1] . ' ' . (strlen($m[2]) === 5 ? $m[2] . ':00' : $m[2]);
            }

            return $value;
        }

        throw new InvalidArgumentException('Unsupported filter value type: ' . get_debug_type($value));
    }
}
