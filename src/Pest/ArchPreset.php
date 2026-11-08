<?php

declare(strict_types=1);

namespace MltStephane\LaravelQuality\Pest;

use Composer\Autoload\ClassLoader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Pest\Expectation;
use Pest\TestSuite;
use Symfony\Component\Finder\Finder;

/**
 * Registers the `laravelQuality` Pest arch preset: layer boundaries and debug calls.
 * Rules on a namespace the project does not have pass without effect.
 */
final class ArchPreset
{
    public const string NAME = 'laravelQuality';

    private const array DEBUG_FUNCTIONS = ['dd', 'dump', 'die', 'ray', 'var_dump', 'print_r', 'exit'];

    /**
     * @param  bool  $requireActionTests  Every App\Actions\X needs a tests/**\/XTest.php.
     */
    public static function register(bool $requireActionTests = false): void
    {
        pest()->presets()->custom(self::NAME, static function (array $userNamespaces) use ($requireActionTests): array {
            $app = in_array('App', $userNamespaces, true) ? 'App' : ($userNamespaces[0] ?? 'App');
            $presentation = ["{$app}\\Livewire", "{$app}\\Http\\Controllers"];

            return [
                expect("{$app}\\Livewire")->not->toUse(DB::class),
                expect("{$app}\\Http")->not->toUse(DB::class),
                expect("{$app}\\Models")->not->toUse(["{$app}\\Http", "{$app}\\Livewire"]),
                expect("{$app}\\Actions")->not->toUse($presentation),
                expect("{$app}\\Services")->not->toUse($presentation),
                expect("{$app}\\Jobs")->toImplement(ShouldQueue::class),
                expect($app)->not->toUse(self::DEBUG_FUNCTIONS),
                ...$requireActionTests ? [self::expectActionsToHaveTests("{$app}\\Actions")] : [],
            ];
        });
    }

    /**
     * Names of the actions found in the given directories that have no {Action}Test.php under the tests directory.
     *
     * @param  array<int, string>  $actionDirectories
     * @return array<int, string>
     */
    public static function actionsWithoutTest(array $actionDirectories, string $testsDirectory): array
    {
        $existing = array_filter($actionDirectories, is_dir(...));

        if ($existing === []) {
            return [];
        }

        $actions = array_map(
            fn ($file): string => $file->getBasename('.php'),
            iterator_to_array(Finder::create()->files()->in($existing)->name('*.php'), false),
        );
        $tests = is_dir($testsDirectory) ? array_map(
            fn ($file): string => $file->getBasename('Test.php'),
            iterator_to_array(Finder::create()->files()->in($testsDirectory)->name('*Test.php'), false),
        ) : [];

        $missing = array_values(array_unique(array_diff($actions, $tests)));
        sort($missing);

        return $missing;
    }

    /**
     * @return Expectation<array<int, string>>
     */
    private static function expectActionsToHaveTests(string $namespace): Expectation
    {
        $missing = self::actionsWithoutTest(
            self::namespaceDirectories($namespace),
            TestSuite::getInstance()->rootPath.DIRECTORY_SEPARATOR.'tests',
        );

        $expectation = expect($missing);
        $expectation->toBe([], 'Actions without a tests/**/{Action}Test.php: '.implode(', ', $missing));

        return $expectation;
    }

    /**
     * Directories the Composer PSR-4 autoloaders map to the namespace.
     *
     * @return array<int, string>
     */
    private static function namespaceDirectories(string $namespace): array
    {
        $namespace .= '\\';
        $directories = [];

        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            foreach ($loader->getPrefixesPsr4() as $prefix => $paths) {
                if (! str_starts_with($namespace, $prefix)) {
                    continue;
                }

                $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($namespace, strlen($prefix)));

                foreach ($paths as $path) {
                    $directories[] = rtrim($path, '/\\').DIRECTORY_SEPARATOR.rtrim($relative, DIRECTORY_SEPARATOR);
                }
            }
        }

        return array_values(array_unique($directories));
    }
}
