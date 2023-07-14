<?php

declare(strict_types=1);

namespace t3n\GraphQL\Controller;

use GraphQL\GraphQL;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use t3n\GraphQL\Context;
use t3n\GraphQL\Exception\InvalidContextException;
use t3n\GraphQL\Log\RequestLoggerInterface;
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
     * @Flow\Inject
     *
     * @var RequestLoggerInterface
     */
    protected $requestLogger;

    /**
     * A list of IANA media types which are supported by this controller
     *
     * @see http://www.iana.org/assignments/media-types/index.html
     *
     * @var string[]
     */
    protected $supportedMediaTypes = ['application/json'];

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

        $endpointConfiguration = $this->endpointConfigurations[$endpoint] ?? [];

        if (isset($endpointConfiguration['context'])) {
            $contextClassname = $endpointConfiguration['context'];
        } else {
            $contextClassname = $this->contextClassName;
        }

        $context = new $contextClassname($this->controllerContext);
        if (! $context instanceof Context) {
            throw new InvalidContextException('The configured Context must extend \t3n\GraphQL\Context', 1545945332);
        }

        if (isset($endpointConfiguration['logRequests']) && $endpointConfiguration['logRequests'] === true) {
            $this->requestLogger->info('Incoming graphql request', ['endpoint' => $endpoint, 'query' => json_encode($query), 'variables' => empty($variables) ? 'none' : $variables]);
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
