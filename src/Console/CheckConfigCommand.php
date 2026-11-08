<?php

declare(strict_types=1);

namespace MltStephane\LaravelQuality\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use MltStephane\LaravelQuality\PhpstanConfig;

/**
 * Fails when the project's PHPStan config weakens the shared setup.
 */
final class CheckConfigCommand extends Command
{
    protected $signature = 'quality:check-config';

    protected $description = 'Fail when phpstan.neon lowers the level below 6 or does not include the shared extension';

    public function handle(Filesystem $files): int
    {
        $config = PhpstanConfig::find($files, base_path());

        if ($config === null) {
            $this->components->error('Aucun phpstan.neon ni phpstan.neon.dist : lance php artisan quality:install.');

            return self::FAILURE;
        }

        $problems = $config->problems();

        foreach ($problems as $problem) {
            $this->components->error($problem);
        }

        if ($problems !== []) {
            return self::FAILURE;
        }

        $this->components->info("{$config->file} : configuration partagée en place.");

        return self::SUCCESS;
    }
}
