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

    private const array THIRD_PARTY_INCLUDES = [
        'vendor/larastan/larastan/extension.neon',
        'vendor/phpstan/phpstan-deprecation-rules/rules.neon',
    ];

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
                $this->completeExistingConfig($files, $config);

                return;
            }
        }

        $this->publishStub($files, 'phpstan.neon.dist.stub', base_path('phpstan.neon.dist'));
    }

    /**
     * Completes an existing config: missing includes are added (the baseline last) and, on the first run,
     * the parameters the shared extension provides are dropped when they hold the same value.
     * A parameter with another value (e.g. a lower level) stays: it overrides the shared one.
     */
    private function completeExistingConfig(Filesystem $files, string $config): void
    {
        $path = base_path($config);
        $neon = $files->get($path);
        $original = $neon;

        if (! str_contains($neon, self::SHARED_EXTENSION)) {
            $neon = (string) preg_replace('/^[ \t]*(level:[ \t]*6|reportUnmatchedIgnoredErrors:[ \t]*true|reportIgnoresWithoutComments:[ \t]*true|parseModelCastsMethod:[ \t]*true)[ \t]*\R/m', '', $neon);
        }

        if (preg_match('/^includes:[ \t]*\R/m', $neon) !== 1) {
            $neon = "includes:\n\n".$neon;
        }

        preg_match('/^includes:[ \t]*\R((?:[ \t]+-.*\R?)*)/m', $neon, $includes, PREG_OFFSET_CAPTURE);
        [$items, $offset] = $includes[1];
        $lines = preg_split('/(?<=\n)/', $items, flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $indent = preg_match('/^([ \t]+)-/', $items, $match) === 1 ? $match[1] : '    ';
        $baselineIndex = array_key_first(array_filter($lines, fn (string $line): bool => str_contains($line, 'baseline')));
        $missing = array_map(
            fn (string $include): string => "{$indent}- {$include}\n",
            array_filter($this->requiredIncludes($files), fn (string $include): bool => ! str_contains($neon, $include)),
        );

        // The baseline must stay last so its ignores apply to every included rule.
        array_splice($lines, $baselineIndex ?? count($lines), 0, $missing);

        if ($baselineIndex === null) {
            $lines[] = "{$indent}- phpstan-baseline.neon\n";
        }

        $neon = substr_replace($neon, implode('', $lines), $offset, strlen($items));

        if ($neon === $original) {
            $this->components->twoColumnDetail($config, 'includes déjà complets');

            return;
        }

        $files->put($path, $neon);
        $this->components->twoColumnDetail($config, 'includes complétés');
    }

    /**
     * @return array<int, string>
     */
    private function requiredIncludes(Filesystem $files): array
    {
        // phpstan/extension-installer already loads Larastan and the deprecation rules.
        $extensionInstaller = $files->isDirectory(base_path('vendor/phpstan/extension-installer'));

        return [
            ...$extensionInstaller ? [] : self::THIRD_PARTY_INCLUDES,
            self::SHARED_EXTENSION,
        ];
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
