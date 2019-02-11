<?php

declare(strict_types=1);

namespace t3n\GraphQL;

use Neos\Flow\Http\Request;
use Neos\Flow\Mvc\Controller\ControllerContext;

class Context
{
    /** @var Request */
    protected $request;

    public function __construct(ControllerContext $controllerContext)
    {
        $this->request = $controllerContext->getRequest()->getMainRequest();
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
