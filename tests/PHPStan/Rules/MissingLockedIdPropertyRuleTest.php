<?php

declare(strict_types=1);

namespace MltStephane\LaravelQuality\Tests\PHPStan\Rules;

use MltStephane\LaravelQuality\PHPStan\Rules\MissingLockedIdPropertyRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<MissingLockedIdPropertyRule>
 */
final class MissingLockedIdPropertyRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new MissingLockedIdPropertyRule;
    }

    public function test_it_reports_unlocked_public_identifier_properties_of_livewire_components(): void
    {
        $this->analyse([__DIR__.'/../../Fixtures/App/Livewire/ShowUser.php'], [
            ['Public Livewire property $id holds an identifier: add #[Locked] so the browser cannot change it.', 10],
            ['Public Livewire property $teamId holds an identifier: add #[Locked] so the browser cannot change it.', 12],
            ['Public Livewire property $ownerId holds an identifier: add #[Locked] so the browser cannot change it.', 14],
            ['Public Livewire property $projectId holds an identifier: add #[Locked] so the browser cannot change it.', 29],
        ]);
    }

    public function test_it_skips_identifiers_marked_as_browser_inputs(): void
    {
        $this->analyse([__DIR__.'/../../Fixtures/App/Livewire/PickRestaurant.php'], [
            ['Public Livewire property $cityId holds an identifier: add #[Locked] so the browser cannot change it.', 24],
        ]);
    }

    public function test_it_ignores_classes_that_are_not_livewire_components(): void
    {
        $this->analyse([__DIR__.'/../../Fixtures/App/Support/PlainObject.php'], []);
    }
}
