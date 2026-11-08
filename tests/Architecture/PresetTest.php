<?php

declare(strict_types=1);

use MltStephane\LaravelQuality\Pest\ArchPreset;

// The package has no actions: the action test requirement passes without effect.
ArchPreset::register(requireActionTests: true);

arch('the shared preset registers and passes on the package itself')
    ->preset()
    ->laravelQuality();

arch('package code follows the php preset')
    ->preset()
    ->php();

arch('package code uses strict types')
    ->expect('MltStephane\LaravelQuality')
    ->toUseStrictTypes();
