<?php

declare(strict_types=1);

namespace t3n\GraphQL\Directive;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ObjectType;
use GraphQLTools\SchemaDirectiveVisitor;
use Neos\Flow\Security\Context as SecurityContext;
use t3n\GraphQL\Service\DefaultFieldResolver;

class AuthDirective extends SchemaDirectiveVisitor
{
    /** @var string[] */
    protected $authenticatedRoles;

    public function injectSecurityContext(SecurityContext $securityContext): void
    {
        $this->authenticatedRoles = array_keys($securityContext->getRoles());
    }

    protected function wrapResolver(FieldDefinition $field): void
    {
        $resolve = $field->resolveFn ?? [DefaultFieldResolver::class, 'resolve'];

        $field->resolveFn = function ($source, $args, $context, $info) use ($resolve) {
            if (! in_array($this->args['required'], $this->authenticatedRoles)) {
                throw new Error('Not allowed');
            }

            return $resolve($source, $args, $context, $info);
        };
    }

    /**
     * @param mixed[] $details
     */
    public function visitFieldDefinition(FieldDefinition $field, array $details): void
    {
        $this->wrapResolver($field);
    }

    public function visitObject(ObjectType $object): void
    {
        foreach ($object->getFields() as $field) {
            $this->wrapResolver($field);
        }
    }
}
