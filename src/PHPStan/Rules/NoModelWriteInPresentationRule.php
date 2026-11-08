<?php

declare(strict_types=1);

namespace MltStephane\LaravelQuality\PHPStan\Rules;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Livewire components and HTTP code read and write models through actions, not Eloquent directly.
 *
 * @implements Rule<CallLike>
 */
final class NoModelWriteInPresentationRule implements Rule
{
    private const array FORBIDDEN_METHODS = [
        'query', 'create', 'forcecreate', 'firstorcreate', 'updateorcreate', 'insert', 'upsert',
        'destroy', 'save', 'update', 'delete', 'forcedelete',
    ];

    private const array ELOQUENT_CLASSES = [Model::class, Builder::class, Relation::class];

    /**
     * @param  array<int, string>  $presentationNamespaces
     */
    public function __construct(private readonly array $presentationNamespaces) {}

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

        if (! $node->name instanceof Identifier || ! in_array($node->name->toLowerString(), self::FORBIDDEN_METHODS, true)) {
            return [];
        }

        $namespace = $this->presentationNamespace($scope);

        if ($namespace === null || ! $this->isEloquent($this->calledOnType($node, $scope))) {
            return [];
        }

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

    private function presentationNamespace(Scope $scope): ?string
    {
        $current = $scope->getNamespace() ?? '';

        foreach ($this->presentationNamespaces as $namespace) {
            $namespace = trim($namespace, '\\');

            if ($current === $namespace || str_starts_with($current, $namespace.'\\')) {
                return $namespace;
            }
        }

        return null;
    }

    private function calledOnType(MethodCall|StaticCall $node, Scope $scope): Type
    {
        if (! $node instanceof StaticCall) {
            return TypeCombinator::removeNull($scope->getType($node->var));
        }

        return $node->class instanceof Name
            ? new ObjectType($scope->resolveName($node->class))
            : $scope->getType($node->class);
    }

    private function isEloquent(Type $type): bool
    {
        foreach (self::ELOQUENT_CLASSES as $class) {
            if ((new ObjectType($class))->isSuperTypeOf($type)->yes()) {
                return true;
            }
        }

        return false;
    }
}
