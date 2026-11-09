<?php

declare(strict_types=1);

namespace MltStephane\LaravelQuality\Tests\PHPStan\Rules;

use MltStephane\LaravelQuality\PHPStan\Rules\NoQueryInPresentationRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoQueryInPresentationRule>
 */
final class NoQueryInPresentationRuleTest extends RuleTestCase
{
    /**
     * @var array<int, string>
     */
    private array $presentationNamespaces = ['App\Livewire', 'App\Http'];

    protected function getRule(): Rule
    {
        return new NoQueryInPresentationRule($this->presentationNamespaces);
    }

    public function test_it_reports_static_query_entry_points_on_models(): void
    {
        $this->analyse([__DIR__.'/../../Fixtures/App/Livewire/ListUsers.php'], [
            ['Eloquent query query() in App\Livewire: read through an action or a query class.', 15],
            ['Eloquent query find() in App\Livewire: read through an action or a query class.', 16],
            ['Eloquent query where() in App\Livewire: read through an action or a query class.', 17],
            ['Eloquent query whereKey() in App\Livewire: read through an action or a query class.', 18],
            ['Eloquent query all() in App\Livewire: read through an action or a query class.', 19],
            ['Eloquent query with() in App\Livewire: read through an action or a query class.', 20],
            ['Eloquent query latest() in App\Livewire: read through an action or a query class.', 21],
        ]);
    }

    public function test_it_ignores_writes(): void
    {
        $this->analyse([__DIR__.'/../../Fixtures/App/Http/Controllers/UserController.php'], []);
    }

    public function test_it_allows_queries_outside_the_presentation_layer(): void
    {
        $this->analyse([__DIR__.'/../../Fixtures/App/Actions/CreateUser.php'], []);
    }

    public function test_the_presentation_namespaces_are_configurable(): void
    {
        $this->presentationNamespaces = ['App\Http'];

        $this->analyse([__DIR__.'/../../Fixtures/App/Livewire/ListUsers.php'], []);
    }
}
