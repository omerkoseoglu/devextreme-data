# Releasing

The three packages are released in this order, because the integrations depend on the core package:

1. **`mihenk/devextreme-data`** (this repository)
   - `composer check` (code style, PHPStan, tests) is green; CI is green on all PHP versions.
   - Move the "Unreleased" entries of `CHANGELOG.md` under the new version and date.
   - `git tag -a v0.1.0 -m "0.1.0" && git push origin main --tags`
   - Packagist picks the tag up (webhook) and lists the version.
2. **`mihenk/devextreme-data-laravel`** and **`mihenk/devextreme-data-symfony`**
   - Wait until the core version is visible on Packagist (their CI installs it from there).
   - Same checklist, then tag `v0.1.0`.

While below 1.0, a minor bump (0.x -> 0.y) may contain breaking changes; the integrations require `^0.1`
and are released together with a compatible core.

## Before the very first release

- The GitHub repositories exist and are public; Packagist packages are submitted.
- `homepage` / `support` are added to each `composer.json`.
- `git archive HEAD | tar -t` contains no tests, demos or CI files (see `.gitattributes`).
