# laravel-quality

Shared static analysis and architecture rules for my Laravel projects: deterministic checks that AI agents (and humans) run instead of prose rules.

## What it ships

- `extension.neon`: base PHPStan parameters (level 6, unmatched or uncommented ignores fail, `parseModelCastsMethod` so Larastan reads the casts declared in `Model::casts()`) and the package's own rules (below).
- `laravelQuality` Pest arch preset: no `DB` facade in Livewire or HTTP, models/actions/services independent from the presentation layer, queued jobs, no debug calls (`dd`, `dump`, `ray`, `exit`…), and optionally a test per action.
- `php artisan quality:install`: publishes `phpstan.neon.dist`, an empty `phpstan-baseline.neon`, `pint.json` (Laravel preset with `declare_strict_types` and `fully_qualified_strict_types`; skipped when the project has a `duster.json`, since Duster runs Pint with its own config), `tests/Architectures/QualityTest.php`, adds the `stan`, `stan:baseline`, `arch` and `check` composer scripts and the `Architecture` test suite. It never overwrites an existing file or script.
  - `--ci` also publishes `.github/workflows/quality.yml`: PHP from `composer.json` (`config.platform.php`, else the lowest version of `require.php`), `composer install`, `key:generate`, `pint --test`, `composer stan`, the `Architecture` suite, gitleaks (Docker image, `detect` on the whole history) and a check that no file matched by `.gitignore` is tracked (`git ls-files -ci --exclude-standard`).
  - `--hooks` also publishes `lefthook.yml`: on pre-commit, `pint --dirty`, `gitleaks protect --staged`, `composer stan`. Install [lefthook](https://lefthook.dev) and gitleaks, then run `lefthook install`.
- `php artisan quality:check-config`: fails when the project's `phpstan.neon` (or `phpstan.neon.dist`) sets a level below 6 or does not include the shared extension. `quality:install` prints the same warnings.
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

CI: run `composer stan` after the app key is generated, and make sure the `Architecture` suite runs, or publish the workflow with `php artisan quality:install --ci`.

Before the package is on Packagist, declare the repository first:

```bash
composer config repositories.laravel-quality vcs https://github.com/MltStephane/laravel-quality
```

An existing `phpstan.neon(.dist)` is completed, not replaced: missing includes (Larastan and deprecation rules unless `phpstan/extension-installer` loads them, the shared extension, then the baseline last) are added, and the parameters the shared extension provides are removed when they hold the same value. A different value, such as a higher `level`, stays and overrides the shared one. Composer does not run a dependency's scripts: run `quality:install` once after `composer require`; it is safe to rerun.

## PHPStan rules

| Rule | Default | Identifier | Reports |
| --- | --- | --- | --- |
| `NoModelWriteInPresentationRule` | on | `laravelQuality.modelWriteInPresentation` | In the presentation namespaces: `create`, `forceCreate`, `firstOrCreate`, `updateOrCreate`, `insert`, `upsert`, `destroy`, `save`, `update`, `delete`, `forceDelete` on a model, an Eloquent builder or a relation. Move them into an action. |
| `NoQueryInPresentationRule` | off | `laravelQuality.queryInPresentation` | In the presentation namespaces: a static query entry point on a model class: `::query()`, `::all()`, `::find()`, `::findOr()`, `::findOrFail()`, `::findMany()`, `::first()`, `::firstOrFail()`, `::firstWhere()`, `::sole()`, `::get()`, `::value()`, `::pluck()`, `::count()`, `::exists()`, `::paginate()`, `::simplePaginate()`, `::cursorPaginate()`, `::cursor()`, `::with()`, `::withCount()`, `::latest()`, `::oldest()`, `::orderBy()`, `::where*()`. The calls chained after them and the reads through a relation of a loaded model (`$user->posts()->get()`) are not reported. |
| `NoStringRoleRule` | on | `laravelQuality.stringRole` | A string literal, or an array holding one, as first argument of `hasRole`, `hasAnyRole`, `assignRole`. Use an enum: `Role::Admin->value`. |
| `NoBroadCatchWithoutRethrowRule` | on | `laravelQuality.broadCatchWithoutRethrow` | `catch (Throwable)` or `catch (Exception)` that is empty or does not rethrow. `report()` alone is reported; a `throw` inside a closure does not count. |
| `MissingLockedIdPropertyRule` | off | `laravelQuality.missingLockedId` | Public property of a Livewire component named `id` or `*Id`, typed `int`, `string` or both (nullable or not), without `#[Locked]`. Properties carrying `#[Validate]`, `#[Url]` or `#[Modelable]` are skipped. |

The presentation namespaces are `App\Livewire`, `App\Http` and `App\NativeComponents` (NativePHP mobile components, which also write to the database).

Each rule can be turned on or off, and the presentation namespaces changed, in the project's `phpstan.neon.dist`:

```neon
parameters:
    laravelQuality:
        noModelWriteInPresentation: true
        noQueryInPresentation: true
        noStringRole: false
        noBroadCatchWithoutRethrow: true
        missingLockedIdProperty: true
        # Lists merge with the shared values: `!` replaces them.
        presentationNamespaces!:
            - App\Livewire
            - App\Http\Controllers
```

Why two rules are off by default:

- `MissingLockedIdPropertyRule`: on 11 projects, 12 of its 26 reports were false positives. An identifier bound by `wire:model` (a patient picker, a filter, a restaurant choice) is meant to be written by the browser, and `#[Locked]` would break it; PHPStan does not read the Blade views, so it cannot tell such a property from an identifier the component sets itself. The rule skips the properties that declare themselves as inputs (`#[Validate]`, `#[Url]`, `#[Modelable]`), which removes part of the noise without reading the views. Turn it on to audit a project, and treat each report as a question: a bound identifier must then be checked against the user's rights where it is used (`authorize()`, a scoped query).
- `NoQueryInPresentationRule`: reading models in `render()` or in a controller is the documented Laravel and Livewire way, and wrapping each read in an action mostly produces one-line classes. On one project, reads were 23 of the 30 reports of the 1.2.0 write rule: they buried the writes, which are the real risk (validation, authorization and side effects bypassed), and swelled the baselines. Turn it on in a project that routes reads through query classes. Writes stay on by default.

The broad catch rule lives in this package rather than coming from [shipmonk/phpstan-rules](https://github.com/shipmonk-rnd/phpstan-rules): shipmonk has no rule for a broad catch that swallows the error (its `requirePreviousExceptionPass` only checks the exceptions that are rethrown), and it enables all its rules by default, so adding it to every project would bring dozens of unrelated errors to triage for one check.

## Use the preset

```php
use MltStephane\LaravelQuality\Pest\ArchPreset;

ArchPreset::register();

arch()->preset()->laravelQuality()->ignoring(['App\Services\LegacyService']);
```

`ArchPreset::register(requireActionTests: true)` also requires a `tests/**/{Action}Test.php` for each class under `App\Actions` (subnamespaces included; the test is matched by class name). It is off by default, and `ignoring()` does not exempt an action from it.

## Upgrade

A release that enables a stricter or more precise rule changes the errors PHPStan reports: entries in an existing baseline may no longer match (`reportUnmatchedIgnoredErrors` then fails). Regenerate it with `composer stan:baseline` after updating.

### 1.2.0 → 1.3.0

- `MissingLockedIdPropertyRule` is now off by default (`missingLockedIdProperty: false`), and skips the properties carrying `#[Validate]`, `#[Url]` or `#[Modelable]`. Set `missingLockedIdProperty: true` to keep it.
- `NoModelWriteInPresentationRule` no longer reports `::query()`: reads moved to the new `NoQueryInPresentationRule` (`laravelQuality.queryInPresentation`), off by default. Set `noQueryInPresentation: true` to keep reporting them. The write errors keep their message and identifier: their baseline entries still match.
- `App\NativeComponents` joins the presentation namespaces: the writes made there are now reported.
- `quality:install` no longer publishes `pint.json` when the project has a `duster.json`. A `pint.json` published by 1.2.0 next to a `duster.json` is not removed: delete it if Duster should keep its own config.
- Regenerate the baseline with `composer stan:baseline`: the entries of the `laravelQuality.missingLockedId` errors and the `query()` entries of `laravelQuality.modelWriteInPresentation` no longer match anything, and `reportUnmatchedIgnoredErrors` fails `composer stan` until they are removed. Run it after choosing which opt-in rules to enable.

### To 1.2

- The four PHPStan rules of 1.2.0 are on by default: run `composer stan:baseline` to freeze the existing errors, or turn a rule off in `phpstan.neon.dist`.
- `quality:install` now publishes `pint.json` when the project has none. Its `declare_strict_types` rule adds `declare(strict_types=1);` to every PHP file on the next `pint` run: review that diff on its own (a call that relied on type coercion now throws a `TypeError`), and regenerate the baseline afterwards.
- Run `php artisan quality:check-config` in CI to catch a level lowered below 6 or a removed include.

## Develop

```bash
composer install
composer test
```
