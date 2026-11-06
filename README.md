# laravel-quality

Shared static analysis and architecture rules for my Laravel projects: deterministic checks that AI agents (and humans) run instead of prose rules.

## What it ships

- `extension.neon`: base PHPStan parameters (level 6, unmatched or uncommented ignores fail).
- `laravelQuality` Pest arch preset: no `DB` facade in Livewire or HTTP, models/actions/services independent from the presentation layer, queued jobs, no debug calls (`dd`, `dump`, `ray`, `exit`…).
- `php artisan quality:install`: publishes `phpstan.neon.dist`, an empty `phpstan-baseline.neon`, `tests/Architectures/QualityTest.php`, adds the `stan`, `stan:baseline`, `arch` and `check` composer scripts and the `Architecture` test suite. It never overwrites an existing file or script.
- Pinned versions of Larastan, PHPStan deprecation rules and Pest.

Project-specific rules (business namespaces, `ignoring()`, baseline) stay in each project.

## Requirements

PHP 8.3+, Laravel 12 or 13, Pest 4.

## Install

```bash
composer require --dev mltstephane/laravel-quality
php artisan quality:install
composer stan:baseline   # existing projects: freeze current errors
composer check
```

CI: run `composer stan` after the app key is generated, and make sure the `Architecture` suite runs.

Before the package is on Packagist, declare the repository first:

```bash
composer config repositories.laravel-quality vcs https://github.com/MltStephane/laravel-quality
```

An existing `phpstan.neon(.dist)` is kept: add `vendor/mltstephane/laravel-quality/extension.neon` to its `includes`.

## Use the preset

```php
use MltStephane\LaravelQuality\Pest\ArchPreset;

ArchPreset::register();

arch()->preset()->laravelQuality()->ignoring(['App\Services\LegacyService']);
```

## Develop

```bash
composer install
composer test
```
