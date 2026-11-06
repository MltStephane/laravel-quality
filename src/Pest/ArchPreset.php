<?php

declare(strict_types=1);

namespace MltStephane\LaravelQuality\Pest;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

/**
 * Registers the `laravelQuality` Pest arch preset: layer boundaries and debug calls.
 * Rules on a namespace the project does not have pass without effect.
 */
final class ArchPreset
{
    public const string NAME = 'laravelQuality';

    private const array DEBUG_FUNCTIONS = ['dd', 'dump', 'die', 'ray', 'var_dump', 'print_r', 'exit'];

    public static function register(): void
    {
        pest()->presets()->custom(self::NAME, static function (array $userNamespaces): array {
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
            ];
        });
    }
}
