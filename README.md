# DevExtreme PHP Data

[![Packagist Version](https://img.shields.io/packagist/v/omerkoseoglu/devextreme-data)](https://packagist.org/packages/omerkoseoglu/devextreme-data) [![PHP Version](https://img.shields.io/packagist/dependency-v/omerkoseoglu/devextreme-data/php)](https://packagist.org/packages/omerkoseoglu/devextreme-data) [![CI](https://github.com/omerkoseoglu/devextreme-data/actions/workflows/ci.yml/badge.svg)](https://github.com/omerkoseoglu/devextreme-data/actions/workflows/ci.yml) [![Downloads](https://img.shields.io/packagist/dt/omerkoseoglu/devextreme-data)](https://packagist.org/packages/omerkoseoglu/devextreme-data) [![License](https://img.shields.io/packagist/l/omerkoseoglu/devextreme-data)](LICENSE)

> **Unofficial.** This is an independent, community-maintained port. It is not affiliated with, endorsed by or supported by Developer Express Inc. "DevExtreme" and "DevExpress" are trademarks of Developer Express Inc.

Server-side data processing for [DevExtreme](https://js.devexpress.com/) widgets in PHP — a port of
[DevExtreme.AspNet.Data](https://github.com/DevExpress/DevExtreme.AspNet.Data).
It understands the request the DevExtreme client sends (`filter`, `sort`, `group`, `skip`, `take`,
`totalSummary`, `groupSummary`, `select`, ...) and answers in the exact shape the client expects.

- **Arrays / iterables / objects** — every feature, in memory (`ArraySource`).
- **SQL through PDO** — SQLite, MySQL/MariaDB, PostgreSQL (`PdoSource`). Filtering, sorting, paging,
  select, counts and summaries run in the database; collapsed groups use a single `GROUP BY`.
- Framework agnostic. Requires PHP 8.1+, `ext-json`, `ext-mbstring` (+ `ext-pdo` for SQL).

## Install

```bash
composer require omerkoseoglu/devextreme-data
```

## Quick start

```php
use DevExtreme\Data\DataSourceLoader;
use DevExtreme\Data\PdoSource;

$source = new PdoSource($pdo, 'orders', primaryKey: ['id']);

header('Content-Type: application/json');
echo json_encode(DataSourceLoader::loadFromRequest($source)); // reads $_GET + $_POST
```

```js
// client
const store = DevExpress.data.AspNet.createStore({ key: 'id', loadUrl: '/api/orders' });
$('#grid').dxDataGrid({ dataSource: store, remoteOperations: true /* ... */ });
```

Arrays work the same way:

```php
echo json_encode(DataSourceLoader::load($arrayOfRows, $_GET));
```

`load()` accepts raw request parameters (JSON strings or decoded arrays) or a `LoadOptions` instance,
and returns a `LoadResult` (`data`, `totalCount`, `groupCount`, `summary`) that is `JsonSerializable`.
Malformed input throws `InvalidArgumentException` — answer with HTTP 400.

## Features

| Feature | Array | PDO |
|---|:-:|:-:|
| Filter: `= <> > >= < <=`, `contains`, `notcontains`, `startswith`, `endswith`, nested and/or, `["!", ...]` | ✔ | ✔ |
| Sort (multi-key, stable), `defaultSort`, `primaryKey` tie-break | ✔ | ✔ |
| Paging, `requireTotalCount`, `isCountQuery` | ✔ | ✔ |
| Grouping (multi-level, `isExpanded: false` → counts only), `requireGroupCount` | ✔ | ✔ |
| Group intervals: numeric ranges, `year quarter month day dayOfWeek hour minute second` | ✔ | ✔ |
| Total & group summaries: `sum min max avg count` | ✔ | ✔ |
| `select`, `preSelect`, dotted paths (`customer.name`) | ✔ | ✔ |
| Custom aggregators (`CustomAggregators::register`) | ✔ | ✔ (computed in PHP) |
| Custom filter operations (`CustomFilterCompilers::registerBinary`) | ✔ | ✔ |
| `paginateViaPrimaryKey`, `remoteSelect`, `remoteGrouping` | – | ✔ |
| Objects, getters (`getX()/isX()`), `ArrayAccess`, `DateTimeInterface`, generators | ✔ | – |

### `LoadOptions`

Properties mirror the client option names: `requireTotalCount`, `requireGroupCount`, `isCountQuery`,
`isSummaryQuery`, `skip`, `take`, `sort`, `group`, `filter`, `totalSummary`, `groupSummary`, `select`,
`preSelect`, `primaryKey`, `defaultSort`, `stringToLower`, `sortByPrimaryKey`, `paginateViaPrimaryKey`,
`remoteSelect`, `remoteGrouping`. Build it with `LoadOptions::fromArray($_GET)` or set properties directly.

### `PdoSource`

```php
new PdoSource(
    $pdo,                                   // must use PDO::ERRMODE_EXCEPTION
    'orders',                               // table/view, or trusted raw FROM with rawFrom: true
    columns: ['id' => 'o.id', 'customer.name' => 'c.name'], // optional whitelist: field => SQL expression
    primaryKey: ['id'],
    where: 'tenant_id = ?', whereParams: [7], // always applied
    // rawFrom: true + fromParams: [...] lets $from be a sub-select with bound parameters
);
```

**Security.** Filter values are always bound parameters. Field names from the client are never
interpolated unless they are a key of the `columns` whitelist or a plain identifier
(`/^[A-Za-z_][A-Za-z0-9_]*$/`). Pass `columns` in production to expose only the fields you intend.
`from`, `where` and the `columns` expressions are *your* trusted SQL.

**Semantics worth knowing**

- `NULL` never satisfies a comparison and is matched by `<>` — identical in both sources, so `["!", ...]` agrees too.
- String case: `ArraySource` compares case-insensitively by default (`stringToLower: true`); `PdoSource` leaves it to
  the database collation (`false`). `contains`/`startswith`/`endswith` are case-insensitive: `LIKE` on SQLite/MySQL (MySQL follows the column collation), `ILIKE` on PostgreSQL.
- Sorting strings: case-insensitive in memory, collation-defined in SQL. `NULL` sorts first ascending in both.
- Groups with a `groupInterval` are ordered by the interval key (Jan..Dec), not by the raw value.

## Extending

```php
use DevExtreme\Data\Aggregation\{Aggregator, CustomAggregators};
use DevExtreme\Data\Filter\{BinaryExpressionInfo, CustomFilterCompilers};
use DevExtreme\Data\Sql\SqlFragment;

CustomAggregators::register('median', fn () => new MedianAggregator()); // extends Aggregator

CustomFilterCompilers::registerBinary(function (BinaryExpressionInfo $i) {
    if ($i->operation !== 'anyof') return null;              // ["category", "anyof", ["a", "b"]]
    return $i->target === 'sql'
        ? new SqlFragment($i->columns->resolve($i->field) . ' IN (?, ?)', $i->value)
        : fn ($item) => in_array(Accessor::read($item, $i->field), $i->value, true);
});
```

## Known limitations

- **Dates and timezones.** ISO-8601 filter values (`2024-05-01T10:00:00.000Z`) are compared as wall-clock time; the
  timezone designator is ignored, nothing is converted. A browser serialises a local `Date` as UTC, so a record saved at
  10:00 in UTC+3 is stored as 07:00 unless your app converts. Store and compare in one timezone.
- **Date-only filters in SQL** (`["at", ">=", "2024-05-01"]`) are bound as the plain string and compared by the database
  with the stored value; `ArraySource` treats it as midnight. Equality on a datetime column therefore differs between the two.
- **PostgreSQL date intervals** (`year`, `month`, ... grouping) need real `date`/`timestamp` columns; `EXTRACT` does not
  accept text columns.
- **Paging order.** Without a primary key (`primaryKey` option) or `defaultSort`, SQL does not guarantee a stable order
  between pages. Set one.
- **Expanded groups load every matching row.** Like the original, `PdoSource` builds expanded groups in PHP from the
  filtered, sorted rows (paging applies to top-level groups afterwards). Collapsed groups (`isExpanded: false`, which is
  what DataGrid and PivotGrid request) use `GROUP BY` and are cheap. Custom aggregators also force this in-PHP path.
- **`ArraySource` holds everything in memory.** Use it for small or already-loaded data, not for large tables.
- **String ordering and equality** follow the database collation in SQL, case-insensitive comparison in memory (see above).
  `LIKE` case behaviour on MySQL follows the column collation.
- **Numbers.** SQL `SUM`/`AVG` results are normalised to `int`/`float`; very large or high-precision `DECIMAL` sums can
  lose precision. Booleans come back as `0`/`1` on most drivers.
- **Rows with `DateTimeInterface` values** (in `ArraySource`) are serialised by `json_encode` as objects; convert them to
  strings first. Group keys that are dates are emitted as ISO-8601.
- **Databases.** SQLite, MySQL/MariaDB and PostgreSQL only. For others extend `Sql\Dialect` and pass it to `PdoSource`.

## Framework integrations

- Laravel / Eloquent: `omerkoseoglu/devextreme-data-laravel`
- Symfony / Doctrine: `omerkoseoglu/devextreme-data-symfony`

## Demo

```bash
composer install
composer demo            # http://localhost:8000
```

A SQLite database (3000 orders) is created on first request. Pages: DataGrid with full CRUD and
server-side everything, PivotGrid with date intervals, and an in-memory array example. Client scripts load from the DevExpress and jsDelivr CDNs.

## Development

```bash
composer test            # PHPUnit (unit + integration + SQL-vs-memory parity)
composer analyse         # PHPStan level 6
composer cs / cs:fix     # PHP-CS-Fixer
```

The same contract test-suite runs against SQLite by default and against MySQL/PostgreSQL when
`DEVEXTREME_TEST_MYSQL_DSN` / `DEVEXTREME_TEST_PGSQL_DSN` are set (see `phpunit.xml.dist`; CI provides both).
**These tests drop and recreate a table named `orders` — point them at a throwaway database.**

## License

MIT. Original work © Developer Express Inc.; see [LICENSE](LICENSE).
