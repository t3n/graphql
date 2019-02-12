<?php

declare(strict_types=1);

namespace t3n\GraphQL\Tests\Functional\Directive;

use t3n\GraphQL\Directive\AuthDirective;
use t3n\GraphQL\Tests\Functional\Directive\Fixtures\QueryResolver;
use t3n\GraphQL\Tests\Functional\GraphQLFunctionTestCase;

class AuthDirectiveTest extends GraphQLFunctionTestCase
{
    /**
     * @test
     */
    public function directiveWillPreventAccessIfNotAuthorized(): void
    {
        $schema = __DIR__ . '/Fixtures/schema.graphql';
        $configuration = [
            'resolvers' => [
                'Query' => QueryResolver::class,
            ],
            'schemaDirectives' => [
                'auth' => AuthDirective::class,
            ],
        ];

        $query = '{ secureValue1 }';

        $result = $this->executeQuery($schema, $configuration, $query);

        static::assertFalse(isset($result['data']));
        static::assertCount(1, $result['errors']);
        static::assertEquals('Not allowed', $result['errors'][0]['message']);
    }

    /**
     * @test
     */
    public function directiveWillGrantAccessIfAuthorized(): void
    {
        $schema = __DIR__ . '/Fixtures/schema.graphql';
        $configuration = [
            'resolvers' => [
                'Query' => QueryResolver::class,
            ],
            'schemaDirectives' => [
                'auth' => AuthDirective::class,
            ],
        ];

        $query = '{ secureValue2 }';

        $result = $this->executeQuery($schema, $configuration, $query);

        static::assertFalse(isset($result['errors']));
        static::assertEquals('secret2', $result['data']['secureValue2']);
    }
}
