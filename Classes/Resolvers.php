<?php

declare(strict_types=1);

namespace t3n\GraphQL;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Neos\Flow\Reflection\ReflectionService;
use ReflectionClass;
use ReflectionMethod;
use t3n\GraphQL\Exception\InvalidResolverException;

class Resolvers implements ArrayAccess, IteratorAggregate
{
    /**
     * @var string[]
     */
    protected static $internalGraphQLMethods = [
        '__schema',
        '__resolveType'
    ];

    /**
     * @Flow\Inject
     *
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /** @var string */
    protected $pathPattern;

    /** @var string */
    protected $generator;

    /** @var mixed[] */
    protected $types = [];

    /** @var mixed[] */
    protected $resolvers = [];

    public static function create(): self
    {
        return new static();
    }

    /**
     * @Flow\CompileStatic
     *
     * @return mixed[]
     */
    public static function aggregateTypes(ObjectManagerInterface $objectManager): array
    {
        $reflectionService = $objectManager->get(ReflectionService::class);
        $classNames = $reflectionService->getAllImplementationClassNamesForInterface(ResolverInterface::class);

        $types = [];
        foreach ($classNames as $className) {
            $classReflection = new ReflectionClass($className);

            $fields = array_filter(
                array_map(
                    static function (ReflectionMethod $method): string {
                        return $method->getName();
                    },
                    $classReflection->getMethods(ReflectionMethod::IS_PUBLIC)
                ),
                static function (string $methodName): bool {
                    return in_array($methodName, self::$internalGraphQLMethods) || substr($methodName, 0, 2) !== '__';
                }
            );

            $types[$className] = [
                'typeName' => preg_replace('/Resolver/', '', $classReflection->getShortName()),
                'fields' => $fields,
            ];
        }

        return $types;
    }

    protected function __construct()
    {
    }

    protected function initialize(): void
    {
        if ($this->resolvers) {
            return;
        }

        $typeMap = static::aggregateTypes($this->objectManager);
        $resolverClasses = [];

        if ($this->generator) {
            $generator = $this->objectManager->get($this->generator);

            if (! $generator instanceof ResolverGeneratorInterface) {
                throw new InvalidResolverException(sprintf('The configured resolver %s generator must implement ResolverGeneratorInterface', $this->generator));
            }
            $resolverClasses = $generator->generate();
        }

        if ($this->pathPattern) {
            foreach ($typeMap as $className => $info) {
                $possibleMatch = str_replace('{Type}', $info['typeName'], $this->pathPattern);

                if ($className !== $possibleMatch) {
                    continue;
                }

                $resolverClasses[$info['typeName']] = $className;
            }
        }

        foreach ($this->types as $typeName => $className) {
            if (! isset($typeMap[$className])) {
                continue;
            }

            $resolverClasses[$typeName] = $className;
        }

        foreach ($resolverClasses as $typeName => $className) {
            $fields = $typeMap[$className]['fields'];
            $this->resolvers[$typeName] = [];
            foreach ($fields as $fieldName) {
                $this->resolvers[$typeName][$fieldName] = function (...$args) use ($className, $fieldName) {
                    return $this->objectManager->get($className)->$fieldName(...$args);
                };
            }
        }
    }

    public function withGenerator(string $generatorClassname): self
    {
        $this->generator = $generatorClassname;
        return $this;
    }

    public function withPathPattern(string $pathPattern): self
    {
        $this->pathPattern = trim($pathPattern, '/');
        return $this;
    }

    public function withType(string $typeName, string $className): self
    {
        $this->types[$typeName] = trim($className, '/');
        return $this;
    }

    /**
     * @param mixed $offset
     */
    public function offsetExists($offset): bool
    {
        $this->initialize();
        return isset($this->resolvers);
    }

    /**
     * @param mixed $offset
     *
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        $this->initialize();
        return $this->resolvers[$offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        // not implemented on purpose
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset): void
    {
        // not implemented on purpose
    }

    public function getIterator(): ArrayIterator
    {
        $this->initialize();
        return new ArrayIterator($this->resolvers);
    }

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        $this->initialize();
        return $this->resolvers;
    }
}
