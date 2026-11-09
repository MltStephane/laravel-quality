<?php

declare(strict_types=1);

namespace MltStephane\LaravelQuality\PHPStan\Rules;

use Illuminate\Database\Eloquent\Model;
use MltStephane\LaravelQuality\PHPStan\PresentationLayer;
use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Opt-in: Livewire components and HTTP code read models through actions or query classes.
 * Only the static entry points of a query on a model class are reported (`User::query()`, `User::where()`…):
 * the calls chained after them, and reads through a relation of a loaded model, are not.
 *
 * @implements Rule<StaticCall>
 */
final class NoQueryInPresentationRule implements Rule
{
    private const array READ_METHODS = [
        'query', 'all', 'find', 'findor', 'findorfail', 'findmany', 'first', 'firstorfail', 'firstwhere', 'sole',
        'get', 'value', 'pluck', 'count', 'exists', 'paginate', 'simplepaginate', 'cursorpaginate', 'cursor',
        'with', 'withcount', 'latest', 'oldest', 'orderby',
    ];

    private readonly PresentationLayer $presentationLayer;

    /**
     * @param  array<int, string>  $presentationNamespaces
     */
    public function __construct(array $presentationNamespaces)
    {
        $this->presentationLayer = new PresentationLayer($presentationNamespaces);
    }

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (! $node->name instanceof Identifier || ! $this->isReadMethod($node->name->toLowerString())) {
            return [];
        }

        $namespace = $this->presentationLayer->namespaceOf($scope);

        if ($namespace === null || ! PresentationLayer::isEloquent(PresentationLayer::calledOnType($node, $scope), [Model::class])) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Eloquent query %s() in %s: read through an action or a query class.',
                $node->name->toString(),
                $namespace,
            ))
                ->identifier('laravelQuality.queryInPresentation')
                ->build(),
        ];
    }

    private function isReadMethod(string $method): bool
    {
        return in_array($method, self::READ_METHODS, true) || str_starts_with($method, 'where');
    }
}
