<?php

declare(strict_types=1);

namespace t3n\GraphQL\Directive;

use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLTools\SchemaDirectiveVisitor;
use Neos\Flow\Annotations as Flow;
use t3n\GraphQL\ResolveCacheInterface;
use t3n\GraphQL\Service\DefaultFieldResolver;

class CachedDirective extends SchemaDirectiveVisitor
{
    /**
     * @Flow\Inject
     *
     * @var ResolveCacheInterface
     */
    protected $cache;

    /**
     * @param mixed[] $details
     */
    public function visitFieldDefinition(FieldDefinition $field, array $details): void
    {
        $resolve = $field->resolveFn ?? [DefaultFieldResolver::class, 'resolve'];

        $field->resolveFn = function ($root, $variables, $context, ResolveInfo $resolveInfo) use ($resolve) {
            $entryIdentifier = md5(implode('.', $resolveInfo->path) . json_encode($variables));

            if ($this->cache->has($entryIdentifier)) {
                return $this->cache->get($entryIdentifier);
            }

            $result = $resolve($root, $variables, $context, $resolveInfo);

            $this->cache->set($entryIdentifier, $result, $this->args['tags'], $this->args['maxAge']);
            return $result;
        };
    }
}
