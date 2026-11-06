<?php

declare(strict_types=1);

namespace MltStephane\LaravelQuality;

use Illuminate\Support\ServiceProvider;
use MltStephane\LaravelQuality\Console\InstallCommand;

final class QualityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);
        }
    }
}
