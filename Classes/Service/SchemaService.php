<?php

declare(strict_types=1);

namespace t3n\GraphQL\Service;

use GraphQL\Language\Parser;
use GraphQL\Type\Schema;
use GraphQLTools\Generate\ConcatenateTypeDefs;
use GraphQLTools\GraphQLTools;
use GraphQLTools\SchemaDirectiveVisitor;
use GraphQLTools\Transforms\Transform;
use InvalidArgumentException;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Utility\Files;
use Neos\Utility\PositionalArraySorter;
use t3n\GraphQL\Resolvers;
use t3n\GraphQL\SchemaEnvelopeInterface;
use t3n\GraphQL\Transform\FlowErrorTransform;
use TypeError;

/**
 * @Flow\Scope("singleton")
 */
class SchemaService
{
    /**
     * @Flow\InjectConfiguration("endpoints")
     *
     * @var mixed[]
     */
    protected $endpoints;

    /**
     * @Flow\Inject
     *
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @Flow\Inject
     *
     * @var VariableFrontend
     */
    protected $schemaCache;

    /**
     * @var Schema[]
     */
    protected $firstLevelCache = [];

    public function getSchemaForEndpoint(string $endpoint): Schema
    {
        if (isset($this->firstLevelCache[$endpoint])) {
            return $this->firstLevelCache[$endpoint];
        }

        $endpointConfiguration = $this->endpoints[$endpoint] ?? null;

        if (! $endpointConfiguration) {
            throw new InvalidArgumentException(sprintf('No schema found for endpoint "%s"', $endpoint));
        }

        if (isset($endpointConfiguration['schemas'])) {
            $schema = $this->getMergedSchemaFromConfigurations($endpointConfiguration);
        } else {
            $schema = $this->getMergedSchemaFromConfigurations([ 'schemas' => [$endpointConfiguration] ]);
        }

        $this->firstLevelCache[$endpoint] = $schema;
        return $schema;
    }

    /**
     * @return mixed[]
     */
    public function getEndpointConfiguration(string $endpoint): ?array
    {
        return $this->endpoints[$endpoint] ?? null;
    }

    protected function getSchemaFromEnvelope(string $envelopeClassName): Schema
    {
        $envelope = $this->objectManager->get($envelopeClassName);
        if (! $envelope instanceof SchemaEnvelopeInterface) {
            throw new TypeError(sprintf('%s has to implement %s', $envelopeClassName, SchemaEnvelopeInterface::class));
        }

        return $envelope->getSchema();
    }

    /**
     * @param mixed[] $configuration
     *
     * @return mixed[]
     */
    protected function getSchemaFromConfiguration(array $configuration): array
    {
        $options = ['typeDefs' => ''];

        if (substr($configuration['typeDefs'], 0, 11) === 'resource://') {
            $options['typeDefs'] = Files::getFileContents($configuration['typeDefs']);
            if ($options['typeDefs'] === false) {
                throw new TypeError(sprintf('File "%s" does not exist', $configuration['typeDefs']));
            }
        } else {
            $options['typeDefs'] = $configuration['typeDefs'];
        }

        $resolvers = Resolvers::create();

        if (isset($configuration['resolverGenerator'])) {
            if ($this->objectManager->isRegistered($configuration['resolverGenerator'])) {
                $resolvers->withGenerator($configuration['resolverGenerator']);
            }
        }

        if (isset($configuration['resolverPathPattern'])) {
            $resolvers->withPathPattern($configuration['resolverPathPattern']);
        }

        if (isset($configuration['resolvers']) && is_array($configuration['resolvers'])) {
            foreach ($configuration['resolvers'] as $typeName => $resolverClass) {
                $resolvers->withType($typeName, $resolverClass);
            }
        }

        $options['resolvers'] = $resolvers;

        if (isset($configuration['schemaDirectives']) && is_array($configuration['schemaDirectives'])) {
            $options['schemaDirectives'] = [];
            foreach ($configuration['schemaDirectives'] as $directiveName => $schemaDirectiveVisitor) {
                $options['schemaDirectives'][$directiveName] = new $schemaDirectiveVisitor();
            }
        }

        if (isset($configuration['transforms']) && is_array($configuration['transforms'])) {
            $options['transforms'] = array_map(
                static function (string $transformClassName): Transform {
                    return new $transformClassName();
                },
                $configuration['transforms']
            );
        }

        return $options;
    }

    /**
     * @param mixed[] $configuration
     *
     * @return mixed[]
     */
    protected function getMergedSchemaFromConfigurations(array $configuration): Schema
    {
        $schemaConfigurations = (new PositionalArraySorter($configuration['schemas']))->toArray();

        $executableSchemas = [];

        // Determine default error transformer
        if (array_key_exists('errorTransform', $configuration) && ! empty($configuration['errorTransform']) && class_exists($configuration['errorTransform'])) {
            $transforms = [new $configuration['errorTransform']()];
        } else {
            $transforms = [new FlowErrorTransform()];
        }

        $options = [
            'typeDefs' => [],
            'resolvers' => [],
            'schemaDirectives' => [],
            'resolverValidationOptions' => ['allowResolversNotInSchema' => true],
        ];

        foreach ($schemaConfigurations as $schemaConfiguration) {
            if (isset($schemaConfiguration['schemaEnvelope'])) {
                $executableSchemas[] = $this->getSchemaFromEnvelope($schemaConfiguration['schemaEnvelope']);
            } else {
                $schemaInfo = $this->getSchemaFromConfiguration($schemaConfiguration);
                $options['typeDefs'][] = $schemaInfo['typeDefs'];
                $options['resolvers'] = array_merge_recursive($options['resolvers'], $schemaInfo['resolvers']->toArray());
                $options['schemaDirectives'] = array_merge($options['schemaDirectives'], $schemaInfo['schemaDirectives'] ?? []);
                $transforms = array_merge($transforms, $schemaInfo['transforms'] ?? []);
            }
        }

        if (isset($configuration['schemaDirectives'])) {
            foreach ($configuration['schemaDirectives'] as $directiveName => $schemaDirectiveVisitorClassName) {
                $schemaDirectiveVisitor = new $schemaDirectiveVisitorClassName();

                if (! $schemaDirectiveVisitor instanceof SchemaDirectiveVisitor) {
                    throw new TypeError(sprintf('%s has to extend %s', $schemaDirectiveVisitorClassName, SchemaDirectiveVisitor::class));
                }

                $options['schemaDirectives'][$directiveName] = $schemaDirectiveVisitor;
            }
        }

        $schema = null;
        if (count($options['typeDefs']) > 0) {
            $cacheIdentifier = md5(serialize($options['typeDefs']));
            if ($this->schemaCache->has($cacheIdentifier)) {
                $options['typeDefs'] = $this->schemaCache->get($cacheIdentifier);
            } else {
                $options['typeDefs'] = Parser::parse(ConcatenateTypeDefs::invoke($options['typeDefs']));
                $this->schemaCache->set($cacheIdentifier, $options['typeDefs']);
            }
            $schema = GraphQLTools::makeExecutableSchema($options);
        }

        if ($schema) {
            $executableSchemas[] = $schema;
        }

        if (count($executableSchemas) > 1) {
            $schema = GraphQLTools::mergeSchemas(['schemas' => $executableSchemas]);
        } else {
            $schema = $executableSchemas[0];
        }

        if (isset($configuration['transforms'])) {
            $transformConfiguration = (new PositionalArraySorter($configuration['transforms']))->toArray();

            foreach ($transformConfiguration as $transformClassName) {
                $transforms[] = new $transformClassName();
            }
        }

        return GraphQLTools::transformSchema($schema, $transforms);
    }
}
