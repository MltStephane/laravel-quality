<?php

declare(strict_types=1);

namespace MltStephane\LaravelQuality\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Roles are named by an enum (`Role::Admin->value`), never by a string literal that a typo breaks silently.
 *
 * @implements Rule<CallLike>
 */
final class NoStringRoleRule implements Rule
{
    private const array ROLE_METHODS = ['hasrole', 'hasanyrole', 'assignrole'];

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        // A nullsafe call also reaches the rule as a MethodCall.
        if (! $node instanceof MethodCall) {
            return [];
        }

        if (! $node->name instanceof Identifier || ! in_array($node->name->toLowerString(), self::ROLE_METHODS, true)) {
            return [];
        }

        $first = $node->getArgs()[0] ?? null;

        if (! $first instanceof Arg || ! $this->containsStringLiteral($first->value)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Role passed as a string literal to %s(): use an enum case (Role::Admin->value).',
                $node->name->toString(),
            ))
                ->identifier('laravelQuality.stringRole')
                ->build(),
        ];
    }

    private function containsStringLiteral(Node $value): bool
    {
        if ($value instanceof String_) {
            return true;
        }

        if (! $value instanceof Array_) {
            return false;
        }

        foreach ($value->items as $item) {
            if ($item->value instanceof String_) {
                return true;
            }
        }

        return false;
    }
}
