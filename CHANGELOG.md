# Changelog

## Unreleased

### Fixed
- PostgreSQL: `contains`/`startswith`/`endswith` are case-insensitive (`ILIKE`), found by running the suite on PostgreSQL 16.

### Added
- `PdoSource` `fromParams` (bindings for a raw sub-select FROM).
- Initial release: `DataSourceLoader`, `ArraySource`, `PdoSource` (SQLite, MySQL, PostgreSQL).
- Filtering, sorting, paging, grouping with intervals, total/group summaries, select/preSelect.
- Custom aggregators and custom filter operations.
- PHPUnit suite (unit, integration, SQL-vs-memory parity), PHPStan, PHP-CS-Fixer, GitHub Actions CI.
- Demo application (DataGrid CRUD, PivotGrid, in-memory array).
