<?php

declare(strict_types=1);

namespace MltStephane\LaravelQuality\Tests\PHPStan\Rules;

use MltStephane\LaravelQuality\PHPStan\Rules\NoModelWriteInPresentationRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoModelWriteInPresentationRule>
 */
final class NoModelWriteInPresentationRuleTest extends RuleTestCase
{
    /**
     * @var array<int, string>
     */
    private array $presentationNamespaces = ['App\Livewire', 'App\Http'];

    protected function getRule(): Rule
    {
        return new NoModelWriteInPresentationRule($this->presentationNamespaces);
    }

    public function test_it_reports_eloquent_calls_in_livewire_and_http(): void
    {
        $this->analyse([
            __DIR__.'/../../Fixtures/App/Livewire/EditUser.php',
            __DIR__.'/../../Fixtures/App/Http/Controllers/UserController.php',
        ], [
            ['Eloquent call query() in App\Livewire: move the query or write into an action.', 14],
            ['Eloquent call create() in App\Livewire: move the query or write into an action.', 15],
            ['Eloquent call firstOrCreate() in App\Livewire: move the query or write into an action.', 16],
            ['Eloquent call destroy() in App\Livewire: move the query or write into an action.', 17],
            ['Eloquent call save() in App\Livewire: move the query or write into an action.', 18],
            ['Eloquent call create() in App\Livewire: move the query or write into an action.', 19],
            ['Eloquent call query() in App\Livewire: move the query or write into an action.', 20],
            ['Eloquent call update() in App\Livewire: move the query or write into an action.', 20],
            ['Eloquent call forceDelete() in App\Livewire: move the query or write into an action.', 21],
            ['Eloquent call delete() in App\Http: move the query or write into an action.', 11],
            ['Eloquent call upsert() in App\Http: move the query or write into an action.', 12],
        ]);
    }

    public function test_it_allows_eloquent_calls_outside_the_presentation_layer(): void
    {
        $this->analyse([__DIR__.'/../../Fixtures/App/Actions/CreateUser.php'], []);
    }

    public function test_the_presentation_namespaces_are_configurable(): void
    {
        $this->presentationNamespaces = ['App\Http'];

        $this->analyse([__DIR__.'/../../Fixtures/App/Livewire/EditUser.php'], []);
    }
}
