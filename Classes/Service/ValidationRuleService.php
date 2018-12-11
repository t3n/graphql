<?php

declare(strict_types=1);

namespace t3n\GraphQL\Service;

use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\ValidationRule;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\PositionalArraySorter;

/**
 * @Flow\Scope("singleton")
 */
class ValidationRuleService
{
    /**
     * @Flow\InjectConfiguration("endpoints")
     * @var ValidationRule[]
     */
    protected $endpoints;

    public function getValidationRulesForEndpoint(string $endpoint) : array
    {
        $rawValidationRulesConfiguration = $this->endpoints[$endpoint]['validationRules'] ?? [];
        $validationRulesConfiguration = (new PositionalArraySorter($rawValidationRulesConfiguration))->toArray();

        $addedRules = array_map(
            static function (array $validationRuleConfiguration) : ValidationRule {
                $className = $validationRuleConfiguration['className'];
                $arguments = $validationRuleConfiguration['arguments'] ?? [];
                return new $className(...array_values($arguments));
            },
            $validationRulesConfiguration
        );

        return array_merge(
            DocumentValidator::allRules(),
            $addedRules
        );
    }
}
