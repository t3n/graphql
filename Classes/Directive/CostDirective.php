<?php

declare(strict_types=1);

namespace t3n\GraphQL\Directive;

use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQLTools\SchemaDirectiveVisitor;
use Neos\Utility\Arrays;
use Neos\Utility\ObjectAccess;

class CostDirective extends SchemaDirectiveVisitor
{
    /** @var Int[] */
    protected $objectComplexityMap;

    public function __construct()
    {
        $this->objectComplexityMap = new \ArrayObject();
    }

    /**
     * @param mixed[] $details
     */
    public function visitFieldDefinition(FieldDefinition $field, array $details): void
    {
        $complexity = $this->args['complexity'] ?? null;
        $multipliers = $this->args['multipliers'];

        $complexityFn = function (int $childrenComplexity, array $args) use ($complexity, $multipliers, $field): int {
            $typeName = Type::getNamedType($field->getType())->name;
            $typeComplexity = $this->objectComplexityMap[$typeName] ?? 1;
            $complexity = $complexity ?? $typeComplexity;

            $multiplier = 0;
            foreach ($multipliers as $multiplierArg) {
                $arg = Arrays::getValueByPath($args, $multiplierArg);
                if ($arg === null) {
                    continue;
                }

                if (is_array($arg)) {
                    $multiplier += count($args);
                } else {
                    $multiplier += $arg;
                }
            }

            $multiplier = max(1, $multiplier);
            return ($complexity  + $childrenComplexity) * $multiplier;
        };

        ObjectAccess::setProperty($field, 'complexityFn', $complexityFn, true);
    }

    public function visitObject(ObjectType $object): void
    {
        $complexity = $this->args['complexity'] ?? null;
        $this->objectComplexityMap[$object->name] = $complexity ?? 1;
    }
}
