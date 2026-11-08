<?php

declare(strict_types=1);

namespace MltStephane\LaravelQuality\Tests\PHPStan\Rules;

use MltStephane\LaravelQuality\PHPStan\Rules\NoStringRoleRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoStringRoleRule>
 */
final class NoStringRoleRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoStringRoleRule;
    }

    public function test_it_reports_string_literal_roles_and_accepts_enum_values(): void
    {
        $this->analyse([__DIR__.'/../../Fixtures/App/Support/RoleChecks.php'], [
            ['Role passed as a string literal to hasRole(): use an enum case (Role::Admin->value).', 12],
            ['Role passed as a string literal to hasAnyRole(): use an enum case (Role::Admin->value).', 13],
            ['Role passed as a string literal to assignRole(): use an enum case (Role::Admin->value).', 14],
        ]);
    }
}
