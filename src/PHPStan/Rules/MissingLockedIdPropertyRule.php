<?php

declare(strict_types=1);

namespace MltStephane\LaravelQuality\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\ClassPropertyNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\IntegerType;
use PHPStan\Type\StringType;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

/**
 * A public Livewire property is writable from the browser: an identifier stored there must be #[Locked].
 *
 * @implements Rule<ClassPropertyNode>
 */
final class MissingLockedIdPropertyRule implements Rule
{
    private const string LIVEWIRE_COMPONENT = 'Livewire\Component';

    private const string LOCKED_ATTRIBUTE = 'Livewire\Attributes\Locked';

    public function getNodeType(): string
    {
        return ClassPropertyNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $name = $node->getName();

        if (! $node->isPublic() || $node->isStatic() || ($name !== 'id' && ! str_ends_with($name, 'Id'))) {
            return [];
        }

        if (! $node->getClassReflection()->isSubclassOf(self::LIVEWIRE_COMPONENT) || ! $this->isIdentifierType($node) || $this->isLocked($node)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Public Livewire property $%s holds an identifier: add #[Locked] so the browser cannot change it.',
                $name,
            ))
                ->identifier('laravelQuality.missingLockedId')
                ->build(),
        ];
    }

    private function isIdentifierType(ClassPropertyNode $node): bool
    {
        $type = $node->getNativeType();

        if ($type === null) {
            return false;
        }

        $type = TypeCombinator::removeNull($type);

        return ! $type->isNull()->yes()
            && (new UnionType([new IntegerType, new StringType]))->isSuperTypeOf($type)->yes();
    }

    private function isLocked(ClassPropertyNode $node): bool
    {
        foreach ($node->getAttrGroups() as $group) {
            foreach ($group->attrs as $attribute) {
                if ($attribute->name->toString() === self::LOCKED_ATTRIBUTE) {
                    return true;
                }
            }
        }

        return false;
    }
}
