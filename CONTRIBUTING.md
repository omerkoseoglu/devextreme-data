# Contributing

1. `composer install`
2. Make your change with a test. New load behaviour belongs in `tests/Support/SourceContractTestCase.php`
   (runs against every source) and, for SQL, in the `ParityTest` option matrix.
3. `composer check` (code style, PHPStan, tests) must pass.

Behaviour should match [DevExtreme.AspNet.Data](https://github.com/DevExpress/DevExtreme.AspNet.Data)
unless documented in the README as a deliberate difference.
