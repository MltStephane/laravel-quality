<?php

declare(strict_types=1);

namespace MltStephane\LaravelQuality\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\ClassLike;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * A `catch (Throwable|Exception)` swallows every failure, bugs included: it must rethrow.
 * Logging with report() alone still hides the error from the caller.
 *
 * @implements Rule<Catch_>
 */
final class NoBroadCatchWithoutRethrowRule implements Rule
{
    private const array BROAD_TYPES = ['throwable', 'exception'];

    public function getNodeType(): string
    {
        return Catch_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $broad = array_values(array_filter(
            $node->types,
            fn (Node\Name $type): bool => in_array(strtolower($type->toString()), self::BROAD_TYPES, true),
        ));

        if ($broad === [] || $this->rethrows($node->stmts)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                $node->stmts === []
                    ? 'Empty catch (%s): catch a narrower exception or rethrow.'
                    : 'catch (%s) without rethrow: catch a narrower exception or rethrow.',
                $broad[0]->toString(),
            ))
                ->identifier('laravelQuality.broadCatchWithoutRethrow')
                ->build(),
        ];
    }

    /**
     * A throw inside a closure, function or class declared in the catch block does not rethrow.
     *
     * @param  array<Node>  $nodes
     */
    private function rethrows(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if ($node instanceof Throw_) {
                return true;
            }

            if ($node instanceof FunctionLike || $node instanceof ClassLike) {
                continue;
            }

            foreach ($node->getSubNodeNames() as $name) {
                $children = $node->{$name};
                $children = is_array($children) ? $children : [$children];

                if ($this->rethrows(array_filter($children, fn (mixed $child): bool => $child instanceof Node))) {
                    return true;
                }
            }
        }

        return false;
    }
}
