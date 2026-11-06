<?php

declare(strict_types=1);

namespace MltStephane\LaravelQuality\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Wires the shared quality setup into the host project. Never overwrites an existing file or script.
 */
final class InstallCommand extends Command
{
    protected $signature = 'quality:install';

    protected $description = 'Install the shared PHPStan config, architecture test, composer scripts and test suite';

    /**
     * @var array<string, string|array<int, string>>
     */
    private const array COMPOSER_SCRIPTS = [
        'stan' => 'phpstan analyse --memory-limit=2G --no-progress',
        'stan:baseline' => 'phpstan analyse --memory-limit=2G --generate-baseline',
        'arch' => 'pest --testsuite=Architecture',
    ];

    public function handle(Filesystem $files): int
    {
        $this->publishPhpstanConfig($files);
        $this->publishStub($files, 'phpstan-baseline.neon.stub', base_path('phpstan-baseline.neon'));
        $this->publishStub($files, 'QualityTest.php.stub', base_path('tests/Architectures/QualityTest.php'));
        $this->addComposerScripts($files);
        $this->addArchitectureTestSuite($files);

        $this->newLine();
        $this->line('CI : ajoute une étape `composer stan` après la génération de la clé, et vérifie que la suite Architecture tourne.');

        return self::SUCCESS;
    }

    private function publishPhpstanConfig(Filesystem $files): void
    {
        foreach (['phpstan.neon', 'phpstan.neon.dist'] as $config) {
            if ($files->exists(base_path($config))) {
                $this->components->warn("{$config} existe déjà : ajoute l'include vendor/mltstephane/laravel-quality/extension.neon à la main.");

                return;
            }
        }

        $this->publishStub($files, 'phpstan.neon.dist.stub', base_path('phpstan.neon.dist'));
    }

    private function publishStub(Filesystem $files, string $stub, string $destination): void
    {
        $relativeDestination = str_replace(base_path().DIRECTORY_SEPARATOR, '', $destination);

        if ($files->exists($destination)) {
            $this->components->twoColumnDetail($relativeDestination, 'conservé');

            return;
        }

        $files->ensureDirectoryExists(dirname($destination));
        $files->copy(dirname(__DIR__, 2)."/stubs/{$stub}", $destination);
        $this->components->twoColumnDetail($relativeDestination, 'créé');
    }

    private function addComposerScripts(Filesystem $files): void
    {
        $path = base_path('composer.json');
        /** @var array{scripts?: array<string, string|array<int, string>>} $composer */
        $composer = json_decode($files->get($path), true, flags: JSON_THROW_ON_ERROR);
        $scripts = $composer['scripts'] ?? [];

        $check = array_values(array_filter(
            ['@lint:check', '@stan', '@arch'],
            fn (string $script): bool => $script !== '@lint:check' || array_key_exists('lint:check', $scripts),
        ));
        $missing = array_diff_key([...self::COMPOSER_SCRIPTS, 'check' => $check], $scripts);

        if ($missing === []) {
            $this->components->twoColumnDetail('composer.json', 'scripts déjà présents');

            return;
        }

        $composer['scripts'] = [...$scripts, ...$missing];
        $json = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $files->put($path, $json.PHP_EOL);
        $this->components->twoColumnDetail('composer.json', 'scripts ajoutés : '.implode(', ', array_keys($missing)));
    }

    private function addArchitectureTestSuite(Filesystem $files): void
    {
        $path = base_path('phpunit.xml');

        if (! $files->exists($path)) {
            $this->components->warn('phpunit.xml introuvable : déclare la suite Architecture (tests/Architectures) à la main.');

            return;
        }

        $phpunit = $files->get($path);

        if (str_contains($phpunit, 'name="Architecture"')) {
            $this->components->twoColumnDetail('phpunit.xml', 'suite Architecture déjà déclarée');

            return;
        }

        $suite = "        <testsuite name=\"Architecture\">\n            <directory>tests/Architectures</directory>\n        </testsuite>\n    </testsuites>";
        $files->put($path, preg_replace('#\s*</testsuites>#', "\n".$suite, $phpunit, 1));
        $this->components->twoColumnDetail('phpunit.xml', 'suite Architecture ajoutée');
    }
}
