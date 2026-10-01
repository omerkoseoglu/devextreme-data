<?php

declare(strict_types=1);

namespace DevExtreme\Data;

use DevExtreme\Data\Aggregation\AggregateCalculator;
use DevExtreme\Data\Contracts\DataSourceInterface;
use DevExtreme\Data\Filter\Comparison;
use DevExtreme\Data\Filter\FilterParser;
use DevExtreme\Data\Filter\Logical;
use DevExtreme\Data\Filter\Node;
use DevExtreme\Data\Grouping\GroupKey;
use DevExtreme\Data\Select\SelectHelper;
use DevExtreme\Data\Sql\ColumnResolver;
use DevExtreme\Data\Sql\Dialect;
use DevExtreme\Data\Sql\RemoteGroupTransformer;
use DevExtreme\Data\Sql\SqlFilterCompiler;
use DevExtreme\Data\Sql\SqlFragment;
use InvalidArgumentException;
use LogicException;
use PDO;
use PDOStatement;

/**
 * Processes a DevExtreme load request on a SQL table or view through PDO.
 *
 * Filtering, sorting, paging, select, total count, total/group summaries and collapsed grouping
 * are executed by the database. Expanded groups are built in PHP from the filtered, sorted rows
 * (exactly like the LINQ-to-SQL behaviour of DevExtreme.AspNet.Data).
 *
 * Field names from the client are never interpolated unless they are plain identifiers or keys of
 * the `$columns` whitelist; filter values are always bound parameters.
 */
final class PdoSource implements DataSourceInterface
{
    private readonly Dialect $dialect;
    private readonly ColumnResolver $columns;
    private readonly string $from;

    /**
     * @param string                      $from           A table/view name (optionally schema-qualified), or a trusted raw FROM
     *                                                    clause (joins, sub-selects) when `$rawFrom` is true
     * @param array<string, string>|null  $columns        Whitelist: field name => trusted SQL expression
     *                                                    (e.g. `['customer.name' => 'c.name']`). When null, any plain
     *                                                    identifier is accepted as a column of `$from`.
     * @param list<string>                $primaryKey     Default primary key (used for stable sorting and keyset paging)
     * @param string|null                 $where          A trusted base condition that is always applied (e.g. tenant scope)
     * @param list<mixed>                 $whereParams    Bindings for `?` placeholders in `$where`
     * @param Dialect|null                $dialect        Detected from the PDO driver when omitted
     * @param bool                        $rawFrom        Treat `$from` as raw SQL instead of a table name
     * @param list<mixed>                 $fromParams     Bindings for `?` placeholders inside a raw `$from` (e.g. a sub-select)
     * @param bool                        $normalizeDates Rewrite ISO-8601 date-time filter values ("2024-05-01T10:00:00.000Z")
     *                                                    to "2024-05-01 10:00:00" (no timezone conversion)
     */
    public function __construct(
        private readonly PDO $pdo,
        string $from,
        ?array $columns = null,
        private readonly array $primaryKey = [],
        private readonly ?string $where = null,
        private readonly array $whereParams = [],
        ?Dialect $dialect = null,
        bool $rawFrom = false,
        private readonly bool $normalizeDates = true,
        private readonly array $fromParams = [],
    ) {
        if ($pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new InvalidArgumentException('PdoSource requires the PDO connection to use PDO::ERRMODE_EXCEPTION.');
        }

        $this->dialect = $dialect ?? Dialect::fromPdo($pdo);
        $this->columns = new ColumnResolver($this->dialect, $columns);
        $this->from = $rawFrom ? $from : $this->quoteTable($from);
    }

    public function load(LoadOptions $options): LoadResult
    {
        $ctx = new LoadContext($this->prepareOptions($options), stringToLowerDefault: false, remoteGroupingDefault: true);
        $where = $this->buildWhere($ctx);

        if ($ctx->isCountQuery()) {
            return new LoadResult(totalCount: $this->count($where));
        }

        if ($ctx->isSummaryQuery()) {
            return $this->loadAggregatesOnly($ctx, $where);
        }

        if ($ctx->useRemoteGrouping() && $ctx->shouldEmptyGroups()) {
            return $this->loadRemoteGroups($ctx, $where);
        }

        $deferPaging = $ctx->hasGroups()
            || (!$ctx->useRemoteGrouping() && !$ctx->summaryIsTotalCountOnly() && $ctx->hasSummary());

        $rows = $this->loadRows($ctx, $where, $deferPaging, true);

        $projection = $this->projection($ctx);
        if ($projection !== null) {
            $rows = SelectHelper::evaluate($rows, $projection);
        }

        $result = new LoadResult();

        if ($ctx->isRemoteTotalSummary()) {
            $totals = $this->queryGroups($ctx, $where, [], false);
            $result->summary = $totals['totals'];
            if ($ctx->requireTotalCount()) {
                $result->totalCount = $totals['totalCount'];
            }
            $result->data = $rows;

            return $result;
        }

        return Pipeline::assemble($rows, $ctx, fn (): int => $this->count($where), $result, $deferPaging);
    }

    // ---- option preparation ----------------------------------------------------------------

    private function prepareOptions(LoadOptions $options): LoadOptions
    {
        $prepared = clone $options;

        if ($prepared->primaryKey === [] && $this->primaryKey !== []) {
            $prepared->primaryKey = $this->primaryKey;
        }

        if ($prepared->remoteGrouping !== false) {
            foreach ([...$prepared->totalSummary, ...$prepared->groupSummary] as $summary) {
                if (!$summary->isBuiltIn()) {
                    // custom aggregators can only run in PHP
                    $prepared->remoteGrouping = false;
                    break;
                }
            }
        }

        return $prepared;
    }

    // ---- row loading -----------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function loadRows(LoadContext $ctx, SqlFragment $where, bool $deferPaging, bool $sorted): array
    {
        $take = $deferPaging ? 0 : $ctx->take();
        $skip = $deferPaging ? 0 : $ctx->skip();
        $orderBy = $sorted && $ctx->hasAnySort() ? $this->buildOrderBy($ctx->fullSort()) : '';

        if (!$deferPaging && $ctx->paginateViaPrimaryKey() && $take > 0) {
            if (!$ctx->hasPrimaryKey()) {
                throw new LogicException('paginateViaPrimaryKey requires a primary key: set LoadOptions::$primaryKey or the PdoSource primaryKey argument.');
            }

            $keyColumns = implode(', ', array_map(
                fn (string $key): string => $this->columns->resolve($key) . ' AS ' . $this->dialect->quoteIdentifier($key),
                $ctx->primaryKey(),
            ));

            $keys = $this->fetchAll(
                sprintf('SELECT %s FROM %s%s%s%s', $keyColumns, $this->from, $this->whereClause($where), $orderBy, $this->dialect->limitOffset($take, $skip)),
                $where->params,
            );

            if ($keys === []) {
                return [];
            }

            $keyFilter = $this->compileKeyFilter($ctx, $keys);
            $where = SqlFragment::all($this->baseWhere(), $keyFilter);
            $take = 0;
            $skip = 0;
        }

        return $this->fetchAll(
            sprintf('SELECT %s FROM %s%s%s%s', $this->selectList($ctx), $this->from, $this->whereClause($where), $orderBy, $this->dialect->limitOffset($take, $skip)),
            $where->params,
        );
    }

    /**
     * @param list<array<string, mixed>> $keys
     */
    private function compileKeyFilter(LoadContext $ctx, array $keys): SqlFragment
    {
        $primaryKey = $ctx->primaryKey();
        $tuples = [];

        foreach ($keys as $row) {
            $conditions = array_map(
                static fn (string $key): Node => new Comparison($key, '=', $row[$key] ?? null),
                $primaryKey,
            );
            $tuples[] = count($conditions) === 1 ? $conditions[0] : new Logical($conditions, true);
        }

        $filter = count($tuples) === 1 ? $tuples[0] : new Logical($tuples, false);

        return (new SqlFilterCompiler($this->dialect, $this->columns, false, false))->compile($filter);
    }

    /**
     * Fields that must be projected/nested in PHP after the query, or null when the rows are already final.
     * Dotted field names ("customer.name") come back from SQL as flat keys and are re-nested here.
     *
     * @return list<string>|null
     */
    private function projection(LoadContext $ctx): ?array
    {
        if ($ctx->hasAnySelect() && !$ctx->useRemoteSelect()) {
            return $ctx->fullSelect();
        }

        $fields = $this->queriedFields($ctx);

        foreach ($fields ?? [] as $field) {
            if (str_contains($field, '.')) {
                return $fields;
            }
        }

        return null;
    }

    /**
     * @return list<string>|null null means "*"
     */
    private function queriedFields(LoadContext $ctx): ?array
    {
        if ($ctx->hasAnySelect() && $ctx->useRemoteSelect()) {
            return $ctx->fullSelect();
        }

        return $this->columns->knownFields();
    }

    private function selectList(LoadContext $ctx): string
    {
        $fields = $this->queriedFields($ctx);
        if ($fields === null) {
            return '*';
        }

        return implode(', ', array_map(
            fn (string $field): string => $this->columns->resolve($field) . ' AS ' . $this->dialect->quoteIdentifier($field),
            $fields,
        ));
    }

    // ---- aggregates & grouping -------------------------------------------------------------

    private function loadAggregatesOnly(LoadContext $ctx, SqlFragment $where): LoadResult
    {
        $result = new LoadResult();

        if (!$ctx->hasTotalSummary()) {
            return $result;
        }

        if ($ctx->summaryIsTotalCountOnly()) {
            $result->summary = array_fill(0, count($ctx->totalSummary()), $this->count($where));
        } elseif ($ctx->useRemoteGrouping()) {
            $result->summary = $this->queryGroups($ctx, $where, [], false)['totals'];
        } else {
            $rows = $this->loadRows($ctx, $where, true, false);
            $result->summary = (new AggregateCalculator($rows, $ctx->totalSummary(), []))->run();
        }

        return $result;
    }

    private function loadRemoteGroups(LoadContext $ctx, SqlFragment $where): LoadResult
    {
        $groups = $ctx->groups();
        $remotePaging = $ctx->hasPaging() && count($groups) === 1;

        $grouped = $this->queryGroups($ctx, $where, $groups, $remotePaging);
        $result = new LoadResult();
        $data = $grouped['groups'];

        if ($remotePaging) {
            if ($ctx->hasTotalSummary()) {
                $totals = $this->queryGroups($ctx, $where, [], false);
                $result->summary = $totals['totals'];
                if ($ctx->requireTotalCount()) {
                    $result->totalCount = $totals['totalCount'];
                }
            } elseif ($ctx->requireTotalCount()) {
                $result->totalCount = $this->count($where);
            }

            if ($ctx->requireGroupCount()) {
                $result->groupCount = $this->countGroups($where, $groups[0]);
            }
        } else {
            $result->summary = $grouped['totals'];
            if ($ctx->requireTotalCount()) {
                $result->totalCount = $grouped['totalCount'];
            }
            if ($ctx->requireGroupCount()) {
                $result->groupCount = count($data);
            }

            $data = Pipeline::paginate($data, $ctx->skip(), $ctx->take());
        }

        $result->data = $data;

        return $result;
    }

    /**
     * Runs one `GROUP BY` query over all levels and rebuilds groups, group summaries and totals.
     *
     * @param list<GroupingInfo> $groups
     *
     * @return array{groups: list<Group>, totals: list<mixed>|null, totalCount: int}
     */
    private function queryGroups(LoadContext $ctx, SqlFragment $where, array $groups, bool $paged): array
    {
        $keyExpressions = array_map($this->keyExpression(...), $groups);

        $select = $keyExpressions;
        $select[] = 'COUNT(*)';

        $summaries = [...$ctx->totalSummary(), ...($groups === [] ? [] : $ctx->groupSummary())];
        foreach ($summaries as $summary) {
            array_push($select, ...$this->aggregateColumns($summary));
        }

        $sql = sprintf('SELECT %s FROM %s%s', implode(', ', $select), $this->from, $this->whereClause($where));

        if ($keyExpressions !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $keyExpressions);
            $sql .= ' ORDER BY ' . implode(', ', array_map(
                fn (GroupingInfo $g, string $expression): string => $expression . ' ' . $this->dialect->orderDirection($g->desc),
                $groups,
                $keyExpressions,
            ));
        }

        if ($paged) {
            $sql .= $this->dialect->limitOffset($ctx->take(), $ctx->skip());
        }

        $stmt = $this->execute($sql, $where->params);
        /** @var list<list<mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);

        return RemoteGroupTransformer::transform(
            $rows,
            $groups,
            $ctx->totalSummary(),
            $groups === [] ? [] : $ctx->groupSummary(),
        );
    }

    /**
     * @return list<string>
     */
    private function aggregateColumns(SummaryInfo $summary): array
    {
        if ($summary->summaryType === SummaryInfo::COUNT) {
            return [];
        }

        $column = $this->columns->resolve($summary->selector);

        return match ($summary->summaryType) {
            SummaryInfo::SUM => ["SUM($column)"],
            SummaryInfo::MIN => ["MIN($column)"],
            SummaryInfo::MAX => ["MAX($column)"],
            SummaryInfo::AVG => ["SUM($column)", "COUNT($column)"],
            default => throw new InvalidArgumentException(sprintf('Unsupported summary type "%s".', $summary->summaryType)),
        };
    }

    private function countGroups(SqlFragment $where, GroupingInfo $group): int
    {
        $sql = sprintf(
            'SELECT COUNT(*) FROM (SELECT 1 FROM %s%s GROUP BY %s) AS g',
            $this->from,
            $this->whereClause($where),
            $this->keyExpression($group),
        );

        return (int) $this->execute($sql, $where->params)->fetchColumn();
    }

    private function keyExpression(GroupingInfo $group): string
    {
        $column = $this->columns->resolve($group->selector);
        $interval = $group->groupInterval;

        if ($interval === null || $interval === '') {
            return $column;
        }

        if (is_numeric($interval)) {
            return $this->dialect->truncateToInterval($column, $interval);
        }

        if (in_array($interval, GroupKey::DATE_INTERVALS, true)) {
            return $this->dialect->datePart($interval, $column);
        }

        throw new InvalidArgumentException(sprintf('Unsupported group interval "%s".', $interval));
    }

    // ---- SQL building blocks ---------------------------------------------------------------

    private function buildWhere(LoadContext $ctx): SqlFragment
    {
        $filter = new SqlFragment('');

        if ($ctx->hasFilter()) {
            /** @var non-empty-list<mixed> $criteria */
            $criteria = $ctx->options->filter;
            $filter = (new SqlFilterCompiler($this->dialect, $this->columns, $ctx->useStringToLower(), $this->normalizeDates))
                ->compile(FilterParser::parse($criteria));
        }

        return SqlFragment::all($this->baseWhere(), $filter);
    }

    private function baseWhere(): SqlFragment
    {
        return $this->where === null || $this->where === ''
            ? new SqlFragment('')
            : new SqlFragment($this->where, $this->whereParams);
    }

    private function whereClause(SqlFragment $where): string
    {
        return $where->isEmpty() ? '' : ' WHERE ' . $where->sql;
    }

    /**
     * @param list<SortingInfo> $sort
     */
    private function buildOrderBy(array $sort): string
    {
        if ($sort === []) {
            return '';
        }

        return ' ORDER BY ' . implode(', ', array_map(
            fn (SortingInfo $s): string => ($s instanceof GroupingInfo ? $this->keyExpression($s) : $this->columns->resolve($s->selector))
                . ' ' . $this->dialect->orderDirection($s->desc),
            $sort,
        ));
    }

    private function count(SqlFragment $where): int
    {
        return (int) $this->execute(
            sprintf('SELECT COUNT(*) FROM %s%s', $this->from, $this->whereClause($where)),
            $where->params,
        )->fetchColumn();
    }

    private function quoteTable(string $table): string
    {
        $parts = explode('.', $table);

        foreach ($parts as $part) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part) !== 1) {
                throw new InvalidArgumentException(sprintf('Invalid table name "%s". Use rawFrom: true for complex FROM clauses.', $table));
            }
        }

        return implode('.', array_map($this->dialect->quoteIdentifier(...), $parts));
    }

    /**
     * @param list<mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    private function fetchAll(string $sql, array $params): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->execute($sql, $params)->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * @param list<mixed> $params
     */
    private function execute(string $sql, array $params): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);

        // the FROM clause precedes WHERE in every statement this class builds
        $params = [...$this->fromParams, ...$params];

        foreach ($params as $i => $value) {
            [$value, $type] = match (true) {
                $value === null => [null, PDO::PARAM_NULL],
                is_int($value) => [$value, PDO::PARAM_INT],
                is_bool($value) => [(int) $value, PDO::PARAM_INT],
                is_float($value) => [(string) $value, PDO::PARAM_STR],
                default => [(string) $value, PDO::PARAM_STR],
            };

            $stmt->bindValue($i + 1, $value, $type);
        }

        $stmt->execute();

        return $stmt;
    }
}
