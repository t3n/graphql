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
use t3n\GraphQL\Service\ValidationRuleService;

class GraphQLController extends ActionController
{
    /**
     * @Flow\Inject
     * @var SchemaService
     */
    protected $schemaService;

    /**
     * @Flow\Inject
     * @var ValidationRuleService
     */
    protected $validationRuleService;

    /**
     * @Flow\InjectConfiguration("context")
     * @var string
     */
    protected $contextClassName;

    /**
     * @param string $endpoint
     * @param string $query
     * @param array|null $variables
     * @param string $operationName
     * @Flow\SkipCsrfProtection
     */
    public function queryAction(string $endpoint, string $query, ?array $variables = null, ?string $operationName = null) : string
    {
        if ($variables !== null && is_string($this->request->getArgument('variables'))) {
            $variables = json_decode($this->request->getArgument('variables'), true);
        }

        $schema = $this->schemaService->getSchemaForEndpoint($endpoint);
        $validationRules = $this->validationRuleService->getValidationRulesForEndpoint($endpoint);

        $context = new $this->contextClassName($this->controllerContext);

        GraphQL::setDefaultFieldResolver([DefaultFieldResolver::class, 'resolve']);

        $result  = GraphQL::executeQuery(
            $schema,
            $query,
            null,
            $context,
            $variables,
            $operationName,
            [DefaultFieldResolver::class, 'resolve'],
            $validationRules
        );

        $this->response->setHeader('Content-Type', 'application/json');
        return json_encode($result->toArray());
    }
}
