<?php

use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->projectPath = sys_get_temp_dir().'/laravel-quality-'.uniqid();
    $this->files = new Filesystem;
    $this->files->ensureDirectoryExists($this->projectPath);
    $this->files->put($this->projectPath.'/composer.json', json_encode([
        'name' => 'acme/app',
        'scripts' => ['lint:check' => ['pint --test'], 'stan' => 'phpstan analyse app'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $this->files->put($this->projectPath.'/phpunit.xml', <<<'XML'
        <phpunit>
            <testsuites>
                <testsuite name="Unit">
                    <directory>tests/Unit</directory>
                </testsuite>
            </testsuites>
        </phpunit>
        XML);
    $this->app->setBasePath($this->projectPath);
});

afterEach(function (): void {
    $this->files->deleteDirectory($this->projectPath);
});

it('publishes the phpstan config, baseline and architecture test', function (): void {
    $this->artisan('quality:install')->assertSuccessful();

    expect($this->files->get($this->projectPath.'/phpstan.neon.dist'))
        ->toContain('vendor/mltstephane/laravel-quality/extension.neon')
        ->and($this->projectPath.'/phpstan-baseline.neon')->toBeFile()
        ->and($this->files->get($this->projectPath.'/tests/Architectures/QualityTest.php'))
        ->toContain('->preset()->laravelQuality()');
});

it('adds missing composer scripts without overwriting existing ones', function (): void {
    $this->artisan('quality:install')->assertSuccessful();

    $scripts = json_decode($this->files->get($this->projectPath.'/composer.json'), true)['scripts'];

    expect($scripts['stan'])->toBe('phpstan analyse app')
        ->and($scripts['arch'])->toBe('pest --testsuite=Architecture')
        ->and($scripts['check'])->toBe(['@lint:check', '@stan', '@arch']);
});

it('declares the architecture test suite once', function (): void {
    $this->artisan('quality:install')->assertSuccessful();
    $this->artisan('quality:install')->assertSuccessful();

    $phpunit = $this->files->get($this->projectPath.'/phpunit.xml');

    expect(substr_count($phpunit, 'name="Architecture"'))->toBe(1)
        ->and($phpunit)->toContain('<directory>tests/Architectures</directory>');
});

it('includes the shared extension in an existing config before the baseline', function (): void {
    $this->files->put($this->projectPath.'/phpstan.neon.dist', <<<'NEON'
        includes:
            - vendor/larastan/larastan/extension.neon
            - phpstan-baseline.neon

        parameters:
            level: 6
            paths:
                - app
            reportUnmatchedIgnoredErrors: true
            reportIgnoresWithoutComments: true

        NEON);

    $this->artisan('quality:install')->assertSuccessful();

    expect($this->files->get($this->projectPath.'/phpstan.neon.dist'))->toBe(<<<'NEON'
        includes:
            - vendor/larastan/larastan/extension.neon
            - vendor/mltstephane/laravel-quality/extension.neon
            - phpstan-baseline.neon

        parameters:
            paths:
                - app

        NEON);
});

it('keeps project parameters that differ from the shared ones', function (): void {
    $this->files->put($this->projectPath.'/phpstan.neon', "parameters:\n    level: 8\n");

    $this->artisan('quality:install')->assertSuccessful();

    expect($this->files->get($this->projectPath.'/phpstan.neon'))
        ->toBe("includes:\n    - vendor/mltstephane/laravel-quality/extension.neon\n\nparameters:\n    level: 8\n");
});

it('includes the shared extension only once', function (): void {
    $this->files->put($this->projectPath.'/phpstan.neon.dist', "includes:\n    - vendor/mltstephane/laravel-quality/extension.neon\n");

    $this->artisan('quality:install')->assertSuccessful();

    expect(substr_count($this->files->get($this->projectPath.'/phpstan.neon.dist'), 'laravel-quality/extension.neon'))->toBe(1);
});
