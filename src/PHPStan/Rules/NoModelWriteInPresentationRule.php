<?php

declare(strict_types=1);

namespace MltStephane\LaravelQuality\PHPStan\Rules;

use MltStephane\LaravelQuality\PHPStan\PresentationLayer;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Livewire components and HTTP code write models through actions, not Eloquent directly.
 * Reads are NoQueryInPresentationRule's concern.
 *
 * @implements Rule<CallLike>
 */
final class NoModelWriteInPresentationRule implements Rule
{
    private const array WRITE_METHODS = [
        'create', 'forcecreate', 'firstorcreate', 'updateorcreate', 'insert', 'upsert',
        'destroy', 'save', 'update', 'delete', 'forcedelete',
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
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // PHPStan also hands a nullsafe call over as a MethodCall: handling NullsafeMethodCall would report it twice.
        if (! $node instanceof MethodCall && ! $node instanceof StaticCall) {
            return [];
        }

        if (! $node->name instanceof Identifier || ! in_array($node->name->toLowerString(), self::WRITE_METHODS, true)) {
            return [];
        }

        $namespace = $this->presentationLayer->namespaceOf($scope);

        if ($namespace === null || ! PresentationLayer::isEloquent(PresentationLayer::calledOnType($node, $scope))) {
            return [];
        }

        // Message kept from 1.2.0 so the baselines that froze these errors still match.
        return [
            RuleErrorBuilder::message(sprintf(
                'Eloquent call %s() in %s: move the query or write into an action.',
                $node->name->toString(),
                $namespace,
            ))
                ->identifier('laravelQuality.modelWriteInPresentation')
                ->build(),
        ];
    }
}
