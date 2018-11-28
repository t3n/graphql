<?php

declare(strict_types=1);

namespace t3n\GraphQL\Controller;

use GraphQL\GraphQL;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use t3n\GraphQL\Service\DefaultFieldResolver;
use t3n\GraphQL\Service\SchemaService;
use function is_string;
use function json_decode;
use function json_encode;

class GraphQLController extends ActionController
{
    /**
     * @Flow\Inject
     * @var SchemaService
     */
    protected $schemaService;

    /**
     * @Flow\InjectConfiguration("context")
     * @var string
     */
    protected $contextClassName;

    /**
     * @Flow\InjectConfiguration("includeExceptionMessageInOutput")
     * @var bool
     */
    protected $includeExceptionMessageInOutput;

    /**
     * @param string $endpoint
     * @param string $query
     * @param array|null $variables
     * @param string $operationName
     */
    public function queryAction(string $endpoint, string $query, ?array $variables = null, ?string $operationName = null) : string
    {
        if ($variables !== null && is_string($this->request->getArgument('variables'))) {
            $variables = json_decode($this->request->getArgument('variables'), true);
        }

        $schema = $this->schemaService->getSchemaForEndpoint($endpoint);

        $context = new $this->contextClassName($this->controllerContext);
        $result  = GraphQL::executeQuery(
            $schema,
            $query,
            null,
            $context,
            $variables,
            $operationName,
            [DefaultFieldResolver::class, 'resolve']
        );

        $this->response->setHeader('Content-Type', 'application/json');
        return json_encode($result->toArray($this->includeExceptionMessageInOutput));
    }
}
