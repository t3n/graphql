<?php

declare(strict_types=1);

namespace t3n\GraphQL\Tests\Functional\Directive;

use t3n\GraphQL\Directive\CachedDirective;
use t3n\GraphQL\ResolveCacheInterface;
use t3n\GraphQL\Tests\Functional\Directive\Fixtures\QueryResolver;
use t3n\GraphQL\Tests\Functional\GraphQLFunctionTestCase;

class CachedDirectiveTest extends GraphQLFunctionTestCase
{
    /**
     * @var ResolveCacheInterface
     */
    protected $resolveCache;

    public function setUp(): void
    {
        parent::setUp();
        $this->resolveCache = $this->objectManager->get(ResolveCacheInterface::class);
        $this->resolveCache->flush();
    }

    /**
     * @test
     */
    public function cachedDirectiveWillCacheValue(): void
    {
        $schema = __DIR__ . '/Fixtures/schema.graphql';
        $configuration = [
            'resolvers' => [
                'Query' => QueryResolver::class,
            ],
            'schemaDirectives' => [
                'cached' => CachedDirective::class,
            ],
        ];

        $query = '{ cachedValue }';

        $result = $this->executeQuery($schema, $configuration, $query);

        static::assertFalse(isset($result['errors']), 'graphql query did not execute without errors');
        static::assertEquals('cachedResult', $result['data']['cachedValue']);

        /** @var QueryResolver $queryResolver */
        $queryResolver = $this->objectManager->get(QueryResolver::class);
        $queryResolver->currentValue = 'newValue';

        $result = $this->executeQuery($schema, $configuration, $query);
        static::assertEquals('cachedResult', $result['data']['cachedValue']);

        $this->resolveCache->flushByTag('my-test-tag');

        $result = $this->executeQuery($schema, $configuration, $query);
        static::assertEquals('newValue', $result['data']['cachedValue']);
    }
}
