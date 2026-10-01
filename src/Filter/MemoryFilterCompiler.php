<?php

declare(strict_types=1);

namespace DevExtreme\Data\Filter;

use Closure;
use DevExtreme\Data\Support\Accessor;
use DevExtreme\Data\Support\Compare;
use DevExtreme\Data\Support\Str;
use InvalidArgumentException;

/**
 * Turns a filter {@see Node} tree into a predicate over in-memory items (arrays or objects).
 */
final class MemoryFilterCompiler
{
    public function __construct(private readonly bool $stringToLower)
    {
    }

    /**
     * @return Closure(mixed): bool
     */
    public function compile(Node $node): Closure
    {
        if ($node instanceof Logical) {
            $operands = array_map($this->compile(...), $node->operands);

            if ($node->isAnd) {
                return static function (mixed $item) use ($operands): bool {
                    foreach ($operands as $operand) {
                        if (!$operand($item)) {
                            return false;
                        }
                    }

                    return true;
                };
            }

            return static function (mixed $item) use ($operands): bool {
                foreach ($operands as $operand) {
                    if ($operand($item)) {
                        return true;
                    }
                }

                return false;
            };
        }

        if ($node instanceof Not) {
            $operand = $this->compile($node->operand);

            return static fn (mixed $item): bool => !$operand($item);
        }

        if ($node instanceof Comparison) {
            return $this->compileComparison($node);
        }

        throw new InvalidArgumentException('Unknown filter node.');
    }

    /**
     * @return Closure(mixed): bool
     */
    private function compileComparison(Comparison $c): Closure
    {
        if (CustomFilterCompilers::hasCompilers()) {
            $custom = CustomFilterCompilers::tryCompileForMemory(new BinaryExpressionInfo(
                BinaryExpressionInfo::TARGET_MEMORY,
                $c->field,
                $c->operation,
                $c->value,
                $this->stringToLower,
            ));

            if ($custom !== null) {
                return $custom;
            }
        }

        $field = $c->field;

        if ($c->isStringOperation()) {
            return $this->compileStringOperation($c);
        }

        $client = $c->value;
        $lower = $this->stringToLower;

        $test = match ($c->operation) {
            '=' => static fn (int $cmp): bool => $cmp === 0,
            '<>' => static fn (int $cmp): bool => $cmp !== 0,
            '>' => static fn (int $cmp): bool => $cmp > 0,
            '>=' => static fn (int $cmp): bool => $cmp >= 0,
            '<' => static fn (int $cmp): bool => $cmp < 0,
            '<=' => static fn (int $cmp): bool => $cmp <= 0,
            default => throw new InvalidArgumentException(sprintf('Unsupported filter operation "%s".', $c->operation)),
        };

        $isEquality = $c->operation === '=';
        $isInequality = $c->operation === '<>';

        return static function (mixed $item) use ($field, $client, $lower, $test, $isEquality, $isInequality): bool {
            $value = Accessor::read($item, $field);

            if ($value === null || $client === null) {
                return match (true) {
                    $value === null && $client === null => $isEquality,
                    default => $isInequality,
                };
            }

            $cmp = Compare::forFilter($value, $client, $lower);

            return $cmp !== null && $test($cmp);
        };
    }

    /**
     * @return Closure(mixed): bool
     */
    private function compileStringOperation(Comparison $c): Closure
    {
        $field = $c->field;
        $needle = Str::from($c->value);
        $lower = $this->stringToLower;
        if ($lower) {
            $needle = Str::lower($needle);
        }

        $operation = $c->operation;

        return static function (mixed $item) use ($field, $needle, $lower, $operation): bool {
            $haystack = Str::from(Accessor::read($item, $field));
            if ($lower) {
                $haystack = Str::lower($haystack);
            }

            return match ($operation) {
                Comparison::CONTAINS => str_contains($haystack, $needle),
                Comparison::NOT_CONTAINS => !str_contains($haystack, $needle),
                Comparison::STARTS_WITH => str_starts_with($haystack, $needle),
                default => str_ends_with($haystack, $needle),
            };
        };
    }
}
