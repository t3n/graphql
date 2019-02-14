<?php

declare(strict_types=1);

namespace t3n\GraphQL\Tests\Functional\Directive;

use GraphQL\Executor\ExecutionResult;
use GraphQL\GraphQL;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQLTools\GraphQLTools;
use Neos\Flow\Tests\FunctionalTestCase;
use Neos\Utility\Files;
use t3n\GraphQL\Directive\CostDirective;

class CostDirectiveTest extends FunctionalTestCase
{
    protected function execute(string $query, int $maxComplexity = 1): ExecutionResult
    {
        $typeDefs =Files::getFileContents(__DIR__ . '/Fixtures/schema.graphql');

        $schema = GraphQLTools::makeExecutableSchema([
            'typeDefs' => $typeDefs,
            'resolvers' => [
                'Query' => [
                    'cheapTypes' => static function ($_, array $args) {
                        $limit = $args['limit'];
                        return array_fill(
                            0,
                            $limit,
                            ['value1' => 'aaa']
                        );
                    },
                    'expensiveTypes' => static function ($_, array $args) {
                        $limit = $args['limit'];
                        return array_fill(
                            0,
                            $limit,
                            ['value1' => 'aaa', 'value2' => 'bbb', 'value3' => 'ccc']
                        );
                    },
                ],
            ],
            'schemaDirectives' => [
                'cost' => new CostDirective(),
            ],
        ]);

        return GraphQL::executeQuery(
            $schema,
            $query,
            null,
            null,
            null,
            null,
            null,
            [new QueryComplexity($maxComplexity)]
        );
    }

    protected static function assertComplexity(ExecutionResult $result, int $complexity): void
    {
        static::assertCount(1, $result->errors);
        static::assertEquals(
            'Max query complexity should be 1 but got ' . $complexity . '.',
            $result->errors[0]->getMessage()
        );
    }

    /**
     * @test
     *
     * Default complexity is 1 for fields and types, so
     *
     * type CheapType {
     *   value1: String
     * }
     *
     * has a complexity of 2
     *
     * Query.cheapTypes is multiplied with 5 (limit)
     * total complexity will be 2 * 5 = 10
     */
    public function costDirectiveShouldRespectMultiplier(): void
    {
        $query = '{ cheapTypes(limit: 5) { value2 } }';
        $result = $this->execute($query);
        static::assertComplexity($result, 10);
    }

    /**
     * @test
     *
     * type ExpensiveType #cost(complexity: 5) {
     *   value1: String
     * }
     *
     * ExpensiveType.value1 has a complexity of 5 + 1 = 6
     * multiplied with 6 (limit) will result in 6 * 6 = 36
     */
    public function costDirectiveShouldRespectObjects(): void
    {
        $query = '{ expensiveTypes(limit: 6) { value1 } }';
        $result = $this->execute($query);
        static::assertComplexity($result, 36);
    }

    /**
     * @test
     *
     * type ExpensiveType #cost(complexity: 5) {
     *   value2: String #cost(complexity: 3)
     * }
     *
     * expensiveType.value2 has a complexity of 5 + 3 = 8
     * multiplied with 6 (limit) will result in 8 * 6 = 48
     */
    public function costDirectiveShouldRespectObjectsAndFields(): void
    {
        $query = '{ expensiveTypes(limit: 6) { value2 } }';
        $result = $this->execute($query);
        static::assertComplexity($result, 48);
    }
}
