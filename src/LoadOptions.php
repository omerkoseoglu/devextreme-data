<?php

declare(strict_types=1);

namespace DevExtreme\Data;

use InvalidArgumentException;
use JsonException;

/**
 * Data processing settings. Property names follow the DevExtreme client option names.
 *
 * @phpstan-consistent-constructor
 */
class LoadOptions
{
    public bool $requireTotalCount = false;

    public bool $requireGroupCount = false;

    public bool $isCountQuery = false;

    public bool $isSummaryQuery = false;

    public int $skip = 0;

    /** The number of data objects (or top-level groups) to load; 0 means "all". */
    public int $take = 0;

    /** @var list<SortingInfo> */
    public array $sort = [];

    /** @var list<GroupingInfo> */
    public array $group = [];

    /**
     * A DevExtreme filter expression.
     *
     * @var list<mixed>|null
     */
    public ?array $filter = null;

    /** @var list<SummaryInfo> */
    public array $totalSummary = [];

    /** @var list<SummaryInfo> */
    public array $groupSummary = [];

    /** @var list<string> */
    public array $select = [];

    /**
     * Limits {@see $select}: the applied select is the intersection of both.
     *
     * @var list<string>
     */
    public array $preSelect = [];

    /** SQL sources only: whether the database should execute the select (default true). */
    public ?bool $remoteSelect = null;

    /** SQL sources only: whether the database should execute grouping/summaries (default true). */
    public ?bool $remoteGrouping = null;

    /** @var list<string> */
    public array $primaryKey = [];

    /** A field used for sorting when no other sort applies. */
    public ?string $defaultSort = null;

    /** Makes string comparison case-insensitive. Defaults to true for arrays, false for SQL sources. */
    public ?bool $stringToLower = null;

    /** SQL sources only: load keys first, then data by keys. Requires a primary key. */
    public ?bool $paginateViaPrimaryKey = null;

    /** Append the primary key to the sort order (default true). */
    public ?bool $sortByPrimaryKey = null;

    /**
     * Builds options from request parameters as sent by the DevExtreme client
     * ($_GET / $_POST / a decoded JSON body). Structured values may be JSON strings or already-decoded arrays.
     *
     * @param array<string, mixed> $params
     *
     * @throws InvalidArgumentException when a value is malformed
     */
    public static function fromArray(array $params): static
    {
        return static::parse(static fn (string $key): mixed => $params[$key] ?? null);
    }

    /**
     * Same as {@see fromArray()} but reads values through a callback (mirrors DataSourceLoadOptionsParser.Parse).
     *
     * @param callable(string): mixed $valueSource
     */
    public static function parse(callable $valueSource): static
    {
        $o = new static();

        $o->requireTotalCount = self::bool($valueSource('requireTotalCount')) ?? false;
        $o->requireGroupCount = self::bool($valueSource('requireGroupCount')) ?? false;
        $o->isCountQuery = self::bool($valueSource('isCountQuery')) ?? false;
        $o->isSummaryQuery = self::bool($valueSource('isSummaryQuery')) ?? false;
        $o->skip = self::int($valueSource('skip'), 'skip');
        $o->take = self::int($valueSource('take'), 'take');

        $o->sort = array_map(
            SortingInfo::fromArray(...),
            self::list($valueSource('sort'), 'sort'),
        );
        $o->group = array_map(
            GroupingInfo::fromArray(...),
            self::list($valueSource('group'), 'group'),
        );
        $o->totalSummary = array_map(
            SummaryInfo::fromArray(...),
            self::list($valueSource('totalSummary'), 'totalSummary'),
        );
        $o->groupSummary = array_map(
            SummaryInfo::fromArray(...),
            self::list($valueSource('groupSummary'), 'groupSummary'),
        );

        $filter = self::decode($valueSource('filter'), 'filter');
        if (is_array($filter) && $filter !== []) {
            $o->filter = array_values($filter);
        }

        $o->select = self::stringList($valueSource('select'), 'select');
        $o->preSelect = self::stringList($valueSource('preSelect'), 'preSelect');
        $o->primaryKey = self::stringList($valueSource('primaryKey'), 'primaryKey');

        $o->remoteSelect = self::bool($valueSource('remoteSelect'));
        $o->remoteGrouping = self::bool($valueSource('remoteGrouping'));
        $o->stringToLower = self::bool($valueSource('stringToLower'));
        $o->paginateViaPrimaryKey = self::bool($valueSource('paginateViaPrimaryKey'));
        $o->sortByPrimaryKey = self::bool($valueSource('sortByPrimaryKey'));

        $defaultSort = $valueSource('defaultSort');
        $o->defaultSort = is_string($defaultSort) && $defaultSort !== '' ? $defaultSort : null;

        return $o;
    }

    private static function bool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($parsed === null) {
            throw new InvalidArgumentException(sprintf('Cannot convert "%s" to a boolean.', is_scalar($value) ? (string) $value : gettype($value)));
        }

        return $parsed;
    }

    private static function int(mixed $value, string $name): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new InvalidArgumentException(sprintf('"%s" must be an integer.', $name));
    }

    private static function decode(mixed $value, string $name): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        if (trim($value) === '') {
            return null;
        }

        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidArgumentException(sprintf('"%s" is not valid JSON: %s', $name, $e->getMessage()), 0, $e);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function list(mixed $value, string $name): array
    {
        $decoded = self::decode($value, $name);
        if ($decoded === null) {
            return [];
        }

        if (!is_array($decoded)) {
            throw new InvalidArgumentException(sprintf('"%s" must be an array.', $name));
        }

        // a single object instead of a list of objects
        if (!array_is_list($decoded)) {
            $decoded = [$decoded];
        }

        foreach ($decoded as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException(sprintf('"%s" must contain objects.', $name));
            }
        }

        /** @var list<array<string, mixed>> $decoded */
        return $decoded;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value, string $name): array
    {
        $decoded = self::decode($value, $name);
        if ($decoded === null) {
            return [];
        }

        if (is_string($decoded)) {
            return [$decoded];
        }

        if (!is_array($decoded)) {
            throw new InvalidArgumentException(sprintf('"%s" must be an array of strings.', $name));
        }

        $result = [];
        foreach ($decoded as $item) {
            if (!is_string($item)) {
                throw new InvalidArgumentException(sprintf('"%s" must be an array of strings.', $name));
            }
            $result[] = $item;
        }

        return $result;
    }
}
