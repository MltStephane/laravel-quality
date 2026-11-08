<?php

declare(strict_types=1);

namespace MltStephane\LaravelQuality;

use Illuminate\Filesystem\Filesystem;

/**
 * The project's PHPStan config, checked against what the shared setup needs:
 * the shared extension included and no level below the shared one.
 */
final class PhpstanConfig
{
    public const string SHARED_EXTENSION = 'vendor/mltstephane/laravel-quality/extension.neon';

    public const int MINIMUM_LEVEL = 6;

    /**
     * PHPStan reads phpstan.neon before phpstan.neon.dist.
     */
    public const array FILES = ['phpstan.neon', 'phpstan.neon.dist'];

    private function __construct(
        public readonly string $file,
        private readonly string $neon,
    ) {}

    public static function find(Filesystem $files, string $basePath): ?self
    {
        foreach (self::FILES as $file) {
            if ($files->exists($basePath.DIRECTORY_SEPARATOR.$file)) {
                return new self($file, $files->get($basePath.DIRECTORY_SEPARATOR.$file));
            }
        }

        return null;
    }

    public function includesSharedExtension(): bool
    {
        return str_contains($this->neon, 'mltstephane/laravel-quality/extension.neon');
    }

    /**
     * The level the project sets, null when it inherits the shared one.
     */
    public function level(): int|string|null
    {
        if (preg_match('/^[ \t]+level:[ \t]*[\'"]?(\w+)[\'"]?[ \t]*(?:#.*)?$/m', $this->neon, $match) !== 1) {
            return null;
        }

        return ctype_digit($match[1]) ? (int) $match[1] : $match[1];
    }

    /**
     * @return array<int, string>
     */
    public function problems(): array
    {
        $problems = [];
        $level = $this->level();

        if (is_int($level) && $level < self::MINIMUM_LEVEL) {
            $problems[] = sprintf('%s fixe level: %d, en dessous du minimum partagé (%d).', $this->file, $level, self::MINIMUM_LEVEL);
        }

        if (! $this->includesSharedExtension()) {
            $problems[] = sprintf("%s n'inclut pas %s.", $this->file, self::SHARED_EXTENSION);
        }

        return $problems;
    }
}
