<?php

declare(strict_types=1);

namespace MltStephane\LaravelQuality\Tests;

use MltStephane\LaravelQuality\QualityServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [QualityServiceProvider::class];
    }
}
