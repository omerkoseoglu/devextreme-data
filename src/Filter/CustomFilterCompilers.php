<?php

declare(strict_types=1);

namespace DevExtreme\Data\Filter;

use Closure;
use DevExtreme\Data\Sql\SqlFragment;

/**
 * Registry of custom filter condition compilers (the counterpart of `CustomFilterCompilers.RegisterBinaryExpressionCompiler`).
 *
 * A compiler receives a {@see BinaryExpressionInfo} and returns:
 *  - for the "memory" target: a `callable(mixed $item): bool`;
 *  - for the "sql" target: a {@see SqlFragment};
 *  - `null` to let the next compiler (or the built-in logic) handle the condition.
 */
final class CustomFilterCompilers
{
    /** @var list<callable(BinaryExpressionInfo): (callable|SqlFragment|null)> */
    private static array $binary = [];

    /**
     * @param callable(BinaryExpressionInfo): (callable|SqlFragment|null) $compiler
     */
    public static function registerBinary(callable $compiler): void
    {
        self::$binary[] = $compiler;
    }

    public static function clear(): void
    {
        self::$binary = [];
    }

    public static function hasCompilers(): bool
    {
        return self::$binary !== [];
    }

    public static function tryCompileForMemory(BinaryExpressionInfo $info): ?Closure
    {
        foreach (self::$binary as $compiler) {
            $result = $compiler($info);
            if ($result !== null && !$result instanceof SqlFragment) {
                return Closure::fromCallable($result);
            }
        }

        return null;
    }

    public static function tryCompileForSql(BinaryExpressionInfo $info): ?SqlFragment
    {
        foreach (self::$binary as $compiler) {
            $result = $compiler($info);
            if ($result instanceof SqlFragment) {
                return $result;
            }
        }

        return null;
    }
}
