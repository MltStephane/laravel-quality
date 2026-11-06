# laravel-quality

Shared static analysis and architecture rules for my Laravel projects: deterministic checks that AI agents (and humans) run instead of prose rules.

## What it ships

- `extension.neon`: base PHPStan parameters (level 6, unmatched or uncommented ignores fail, `parseModelCastsMethod` so Larastan reads the casts declared in `Model::casts()`).
- `laravelQuality` Pest arch preset: no `DB` facade in Livewire or HTTP, models/actions/services independent from the presentation layer, queued jobs, no debug calls (`dd`, `dump`, `ray`, `exit`…).
- `php artisan quality:install`: publishes `phpstan.neon.dist`, an empty `phpstan-baseline.neon`, `tests/Architectures/QualityTest.php`, adds the `stan`, `stan:baseline`, `arch` and `check` composer scripts and the `Architecture` test suite. It never overwrites an existing file or script.
- Pinned versions of Larastan, PHPStan deprecation rules and Pest.

Project-specific rules (business namespaces, `ignoring()`, baseline) stay in each project.

## Requirements

PHP 8.3+, Laravel 12 or 13, Pest 4 or 5 (Pest 5 needs PHP 8.4+).

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

An existing `phpstan.neon(.dist)` is completed, not replaced: missing includes (Larastan and deprecation rules unless `phpstan/extension-installer` loads them, the shared extension, then the baseline last) are added, and the parameters the shared extension provides are removed when they hold the same value. A different value, such as a higher `level`, stays and overrides the shared one. Composer does not run a dependency's scripts: run `quality:install` once after `composer require`; it is safe to rerun.

## Use the preset

```php
use MltStephane\LaravelQuality\Pest\ArchPreset;

ArchPreset::register();

arch()->preset()->laravelQuality()->ignoring(['App\Services\LegacyService']);
```

## Upgrade

A release that enables a stricter or more precise rule changes the errors PHPStan reports: entries in an existing baseline may no longer match (`reportUnmatchedIgnoredErrors` then fails). Regenerate it with `composer stan:baseline` after updating.

## Develop

```bash
composer install
composer test
```
