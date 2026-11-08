<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Runs PHPStan with Larastan, extension.neon and the given parameters, returns the identifiers of the package's errors.
 *
 * @return array<int, string>
 */
function laravelQualityErrors(string $parameters): array
{
    $directory = sys_get_temp_dir().'/laravel-quality-phpstan-'.uniqid();
    $root = dirname(__DIR__, 2);
    (new Filesystem)->ensureDirectoryExists($directory);
    file_put_contents($directory.'/phpstan.neon', <<<NEON
        includes:
            - {$root}/vendor/larastan/larastan/extension.neon
            - {$root}/extension.neon

        parameters:
            tmpDir: {$directory}/tmp
            paths:
                - {$root}/tests/Fixtures/App
        {$parameters}
        NEON);

    $process = new Process([PHP_BINARY, $root.'/vendor/bin/phpstan', 'analyse', '--configuration='.$directory.'/phpstan.neon', '--error-format=json', '--no-progress'], $root);
    $process->run();
    (new Filesystem)->deleteDirectory($directory);

    if (! str_starts_with($process->getOutput(), '{')) {
        throw new RuntimeException('PHPStan failed: '.$process->getOutput().$process->getErrorOutput());
    }

    /** @var array{files: array<string, array{messages: array<int, array{identifier?: string}>}>} $result */
    $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    $identifiers = [];

    foreach ($result['files'] as $file) {
        foreach ($file['messages'] as $message) {
            $identifiers[] = $message['identifier'] ?? '';
        }
    }

    $identifiers = array_values(array_unique(array_filter($identifiers, fn (string $identifier): bool => str_starts_with($identifier, 'laravelQuality.'))));
    sort($identifiers);

    return $identifiers;
}

it('registers every rule through extension.neon', function (): void {
    expect(laravelQualityErrors(''))->toBe([
        'laravelQuality.broadCatchWithoutRethrow',
        'laravelQuality.missingLockedId',
        'laravelQuality.modelWriteInPresentation',
        'laravelQuality.stringRole',
    ]);
});

it('turns rules off and replaces the presentation namespaces from the project config', function (): void {
    expect(laravelQualityErrors(<<<'NEON'
            laravelQuality:
                noStringRole: false
                noBroadCatchWithoutRethrow: false
                missingLockedIdProperty: false
                presentationNamespaces!:
                    - App\Console
        NEON))->toBe([]);
});
