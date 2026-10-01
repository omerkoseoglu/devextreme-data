<?php

declare(strict_types=1);

namespace DevExtreme\Data;

use DevExtreme\Data\Contracts\DataSourceInterface;

/**
 * Entry point: `DataSourceLoader::load($source, $options)`.
 *
 * `$source` may be an array/iterable of rows (handled in memory), a {@see PdoSource}
 * or any other {@see DataSourceInterface}.
 */
final class DataSourceLoader
{
    /**
     * @param iterable<mixed>|DataSourceInterface  $source
     * @param LoadOptions|array<string, mixed>     $options a LoadOptions instance or raw request parameters
     *
     * @throws \InvalidArgumentException for malformed options or filters
     */
    public static function load(iterable|DataSourceInterface $source, LoadOptions|array $options = []): LoadResult
    {
        if (is_array($options)) {
            $options = LoadOptions::fromArray($options);
        }

        return self::createSource($source)->load($options);
    }

    /**
     * Loads using the current request parameters ($_GET merged with $_POST by default).
     *
     * @param iterable<mixed>|DataSourceInterface $source
     * @param array<string, mixed>|null           $params
     */
    public static function loadFromRequest(iterable|DataSourceInterface $source, ?array $params = null): LoadResult
    {
        return self::load($source, $params ?? array_merge($_GET, $_POST));
    }

    /**
     * @param iterable<mixed>|DataSourceInterface $source
     */
    public static function createSource(iterable|DataSourceInterface $source): DataSourceInterface
    {
        return $source instanceof DataSourceInterface ? $source : new ArraySource($source);
    }
}
