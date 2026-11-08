<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;

beforeEach(function (): void {
    $this->projectPath = sys_get_temp_dir().'/laravel-quality-'.uniqid();
    $this->files = new Filesystem;
    $this->files->ensureDirectoryExists($this->projectPath);
    $this->app->setBasePath($this->projectPath);
});

afterEach(function (): void {
    $this->files->deleteDirectory($this->projectPath);
});

it('passes when the shared extension is included without a lower level', function (string $parameters): void {
    $this->files->put($this->projectPath.'/phpstan.neon.dist', "includes:\n    - vendor/mltstephane/laravel-quality/extension.neon\n{$parameters}");

    $this->artisan('quality:check-config')->assertSuccessful();
})->with([
    'inherited level' => [''],
    'higher level' => ["\nparameters:\n    level: 8\n"],
    'max level' => ["\nparameters:\n    level: max\n"],
]);

it('fails when the level is below 6', function (): void {
    $this->files->put($this->projectPath.'/phpstan.neon.dist', "includes:\n    - vendor/mltstephane/laravel-quality/extension.neon\n\nparameters:\n    level: 5\n");

    $this->artisan('quality:check-config')
        ->expectsOutputToContain('level: 5')
        ->assertFailed();
});

it('fails when the shared extension is not included', function (): void {
    $this->files->put($this->projectPath.'/phpstan.neon.dist', "parameters:\n    level: 6\n");

    $this->artisan('quality:check-config')
        ->expectsOutputToContain("n'inclut pas vendor/mltstephane/laravel-quality/extension.neon")
        ->assertFailed();
});

it('checks phpstan.neon before phpstan.neon.dist, as PHPStan does', function (): void {
    $this->files->put($this->projectPath.'/phpstan.neon.dist', "includes:\n    - vendor/mltstephane/laravel-quality/extension.neon\n");
    $this->files->put($this->projectPath.'/phpstan.neon', "includes:\n    - phpstan.neon.dist\n\nparameters:\n    level: 4\n");

    $this->artisan('quality:check-config')->assertFailed();
});

it('fails without a phpstan config', function (): void {
    $this->artisan('quality:check-config')->assertFailed();
});
