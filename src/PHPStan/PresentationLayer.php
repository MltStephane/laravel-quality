<?php

declare(strict_types=1);

namespace MltStephane\LaravelQuality\PHPStan;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Shared by the rules that keep Eloquent out of the presentation namespaces (Livewire, HTTP…).
 */
final readonly class PresentationLayer
{
    /**
     * @param  array<int, string>  $namespaces
     */
    public function __construct(private array $namespaces) {}

    /**
     * The configured namespace the scope belongs to, null outside the presentation layer.
     */
    public function namespaceOf(Scope $scope): ?string
    {
        $current = $scope->getNamespace() ?? '';

        foreach ($this->namespaces as $namespace) {
            $namespace = trim($namespace, '\\');

            if ($current === $namespace || str_starts_with($current, $namespace.'\\')) {
                return $namespace;
            }
        }

        return null;
    }

    public static function calledOnType(MethodCall|StaticCall $node, Scope $scope): Type
    {
        if (! $node instanceof StaticCall) {
            return TypeCombinator::removeNull($scope->getType($node->var));
        }

        return $node->class instanceof Name
            ? new ObjectType($scope->resolveName($node->class))
            : $scope->getType($node->class);
    }

    /**
     * @param  array<int, class-string>  $classes
     */
    public static function isEloquent(Type $type, array $classes = [Model::class, Builder::class, Relation::class]): bool
    {
        foreach ($classes as $class) {
            if ((new ObjectType($class))->isSuperTypeOf($type)->yes()) {
                return true;
            }
        }

        return false;
    }
}
