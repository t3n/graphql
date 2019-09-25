<?php

declare(strict_types=1);

namespace t3n\GraphQL\Directive;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQLTools\SchemaDirectiveVisitor;
use t3n\GraphQL\ResolveCacheInterface;
use t3n\GraphQL\Service\DefaultFieldResolver;

class RateLimitDirective extends SchemaDirectiveVisitor
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
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

            if (strlen($ipAddress) !== 0) {
                $entryIdentifier = md5($this->name . $this->visitedType->name . $ipAddress);
                $retries = 1;

                if ($this->cache->has($entryIdentifier)) {
                    $retries = $this->cache->get($entryIdentifier);
                    $retries++;
                }

                if ($retries > $this->args['max']) {
                    throw new Error('Rate limit exceeded.');
                }

                $this->cache->set($entryIdentifier, $retries, [$this->visitedType->name], $this->args['seconds']);
            }

            return $resolve($root, $variables, $context, $resolveInfo);
        };
    }
}
