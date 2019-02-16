<?php

declare(strict_types=1);

namespace t3n\GraphQL;

use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\Mvc\RequestInterface;

class Context
{
    /** @var RequestInterface */
    protected $request;

    public function __construct(ControllerContext $controllerContext)
    {
        $this->request = $controllerContext->getRequest()->getMainRequest();
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
