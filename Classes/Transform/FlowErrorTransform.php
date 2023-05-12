<?php

declare(strict_types=1);

namespace t3n\GraphQL\Transform;

use GraphQL\Error\Error;
use GraphQL\Executor\ExecutionResult;
use GraphQLTools\Transforms\Transform;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\ThrowableStorageInterface;
use Psr\Log\LoggerInterface;

class FlowErrorTransform implements Transform
{
    /**
     * @Flow\Inject
     *
     * @var ThrowableStorageInterface
     */
    protected $throwableStorage;

    /**
     * @Flow\Inject
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Flow\InjectConfiguration("includeExceptionMessageInOutput")
     *
     * @var bool
     */
    protected $includeExceptionMessageInOutput;

    public function transformResult(ExecutionResult $result): ExecutionResult
    {
        $result->errors = array_map(function (Error $error) {
            $previousError = $error->getPrevious();
            if (! $previousError instanceof Error) {
                $message = $this->throwableStorage->logThrowable($previousError);
                $this->logger->error('GraphQL response with error. The error has bubbled up to the next nullable field: ' . $message);

                if (! $this->includeExceptionMessageInOutput) {
                    $message = preg_replace('/.* - See also: (.+)\.txt$/s', 'Internal error ($1)', $message);
                }

                return new Error(
                    $message,
                    $error->getNodes(),
                    $error->getSource(),
                    $error->getPositions(),
                    $error->getPath(),
                    $previousError
                );
            }

            return $error;
        }, $result->errors);

        return $result;
    }
}
