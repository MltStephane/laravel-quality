<?php

use MltStephane\LaravelQuality\Pest\ArchPreset;

ArchPreset::register();

arch('the shared preset registers and passes on the package itself')
    ->preset()
    ->laravelQuality();

arch('package code follows the php preset')
    ->preset()
    ->php();

arch('package code uses strict types')
    ->expect('MltStephane\LaravelQuality')
    ->toUseStrictTypes();
