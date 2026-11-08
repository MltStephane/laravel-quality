<?php

declare(strict_types=1);

namespace MltStephane\LaravelQuality\Tests\PHPStan\Rules;

use MltStephane\LaravelQuality\PHPStan\Rules\NoBroadCatchWithoutRethrowRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoBroadCatchWithoutRethrowRule>
 */
final class NoBroadCatchWithoutRethrowRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoBroadCatchWithoutRethrowRule;
    }

    public function test_it_reports_broad_catches_that_do_not_rethrow(): void
    {
        $this->analyse([__DIR__.'/../../Fixtures/App/Support/Catches.php'], [
            ['Empty catch (Throwable): catch a narrower exception or rethrow.', 15],
            ['catch (Exception) without rethrow: catch a narrower exception or rethrow.', 20],
            ['catch (Throwable) without rethrow: catch a narrower exception or rethrow.', 26],
        ]);
    }
}
