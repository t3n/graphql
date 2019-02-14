<?php

declare(strict_types=1);

namespace t3n\GraphQL\Controller;

use GraphQL\GraphQL;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use t3n\GraphQL\Context;
use t3n\GraphQL\Exception\InvalidContextException;
use t3n\GraphQL\Service\DefaultFieldResolver;
use t3n\GraphQL\Service\SchemaService;
use t3n\GraphQL\Service\ValidationRuleService;

class GraphQLController extends ActionController
{
    /**
     * @Flow\Inject
     *
     * @var SchemaService
     */
    protected $schemaService;

    /**
     * @Flow\Inject
     *
     * @var ValidationRuleService
     */
    protected $validationRuleService;

    /**
     * @Flow\InjectConfiguration("context")
     *
     * @var string
     */
    protected $contextClassName;

    /**
     * @Flow\InjectConfiguration("endpoints")
     *
     * @var mixed[]
     */
    protected $endpointConfigurations;

    /**
     * phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingTraversableParameterTypeHintSpecification
     *
     * @Flow\SkipCsrfProtection
     *
     * @param string $endpoint
     * @param string $query
     * @param array|null $variables
     * @param string|null $operationName
     */
    public function queryAction(string $endpoint, string $query, ?array $variables = null, ?string $operationName = null): string
    {
        if ($variables !== null && is_string($this->request->getArgument('variables'))) {
            $variables = json_decode($this->request->getArgument('variables'), true);
        }

        $schema = $this->schemaService->getSchemaForEndpoint($endpoint);
        $validationRules = $this->validationRuleService->getValidationRulesForEndpoint($endpoint);

        if (isset($this->endpointConfigurations[$endpoint]['context'])) {
            $contextClassname = $this->endpointConfigurations[$endpoint]['context'];
        } else {
            $contextClassname = $this->contextClassName;
        }

        $context = new $contextClassname($this->controllerContext);
        if (! $context instanceof Context) {
            throw new InvalidContextException('The configured Context must extend \t3n\GraphQL\Context', 1545945332);
        }

        GraphQL::setDefaultFieldResolver([DefaultFieldResolver::class, 'resolve']);

        $result  = GraphQL::executeQuery(
            $schema,
            $query,
            null,
            $context,
            $variables,
            $operationName,
            null,
            $validationRules
        );

        $this->response->setHeader('Content-Type', 'application/json');
        return json_encode($result->toArray());
    }
}
