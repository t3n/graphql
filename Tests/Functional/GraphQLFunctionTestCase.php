<?php

declare(strict_types=1);

namespace t3n\GraphQL\Tests\Functional;

use GuzzleHttp\Psr7\Uri;
use Neos\Flow\Tests\FunctionalTestCase;
use Neos\Utility\Files;
use t3n\GraphQL\Service\SchemaService;

class GraphQLFunctionTestCase extends FunctionalTestCase
{
    public const TEST_ENDPOINT = 'test-endpoint';

    /**
     * @param mixed[] $endpointConfiguration
     *
     * @return mixed[]
     *
     * @throws \Exception
     */
    protected function executeQuery(string $schemaPath, array $endpointConfiguration, string $query): array
    {
        $endpointConfiguration['typeDefs'] = Files::getFileContents($schemaPath);
        $endpointsConfiguration = [static::TEST_ENDPOINT => $endpointConfiguration];

        $schemaService = $this->objectManager->get(SchemaService::class);
        $this->inject($schemaService, 'endpoints', $endpointsConfiguration);
        $this->inject($schemaService, 'firstLevelCache', []);

        $this->browser->addAutomaticRequestHeader('content-type', 'application/json');
        $response = $this->browser->request(new Uri('http://localhost/api-test/' . static::TEST_ENDPOINT), 'POST', ['query' => $query]);
        $this->browser->removeAutomaticRequestHeader('content-type');

        $content = $response->getBody()->getContents();
        if ($response->getStatusCode() === 500) {
            throw new \Exception($content);
        }

        return json_decode($content, true);
    }
}
