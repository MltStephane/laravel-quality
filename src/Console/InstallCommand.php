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

    private const string SHARED_EXTENSION = 'vendor/mltstephane/laravel-quality/extension.neon';

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
                $this->includeSharedExtension($files, $config);

                return;
            }
        }

        $this->publishStub($files, 'phpstan.neon.dist.stub', base_path('phpstan.neon.dist'));
    }

    /**
     * Adds the shared extension to an existing config and drops the parameters it now provides.
     * A parameter with another value (e.g. a higher level) stays: it overrides the shared one.
     */
    private function includeSharedExtension(Filesystem $files, string $config): void
    {
        $path = base_path($config);
        $neon = $files->get($path);

        if (str_contains($neon, self::SHARED_EXTENSION)) {
            $this->components->twoColumnDetail($config, 'extension partagée déjà incluse');

            return;
        }

        $neon = preg_replace('/^[ \t]*(level:[ \t]*6|reportUnmatchedIgnoredErrors:[ \t]*true|reportIgnoresWithoutComments:[ \t]*true)[ \t]*\R/m', '', $neon);

        if (preg_match('/^includes:[ \t]*\R((?:[ \t]+-.*\R?)*)/m', $neon, $includes, PREG_OFFSET_CAPTURE) === 1) {
            [$items, $offset] = $includes[1];
            $lines = preg_split('/(?<=\n)/', $items, flags: PREG_SPLIT_NO_EMPTY);
            $indent = preg_match('/^([ \t]+)-/', $items, $match) === 1 ? $match[1] : '    ';
            $baselineIndex = array_key_first(array_filter($lines, fn (string $line): bool => str_contains($line, 'baseline')));

            // The baseline must stay last so its ignores apply to every included rule.
            array_splice($lines, $baselineIndex ?? count($lines), 0, ["{$indent}- ".self::SHARED_EXTENSION."\n"]);
            $neon = substr_replace($neon, implode('', $lines), $offset, strlen($items));
        } else {
            $neon = "includes:\n    - ".self::SHARED_EXTENSION."\n\n".$neon;
        }

        $files->put($path, $neon);
        $this->components->twoColumnDetail($config, 'extension partagée incluse');
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
