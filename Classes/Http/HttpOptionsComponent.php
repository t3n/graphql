<?php

declare(strict_types=1);

namespace t3n\GraphQL\Http;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Component\ComponentChain;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Component\ComponentInterface;
use Psr\Http\Message\ServerRequestInterface;

class HttpOptionsComponent implements ComponentInterface
{
    /**
     * @Flow\InjectConfiguration("endpoints")
     *
     * @var mixed[]
     */
    protected $endpoints;

    public function handle(ComponentContext $componentContext): void
    {
        /** @var ServerRequestInterface $httpRequest */
        $httpRequest = $componentContext->getHttpRequest();

        if ($httpRequest->getMethod() !== 'OPTIONS') {
            return;
        }

        // We explode the request target because custom routes like /some/custom/route/<endpoint> are
        // are common. So we double check here if the last part in the route matches a configured
        // endpoint
        $endpoint = explode('/', ltrim($httpRequest->getRequestTarget(), '\/'));

        if (! isset($this->endpoints[end($endpoint)])) {
            return;
        }

        $httpResponse = $componentContext->getHttpResponse();
        $httpResponse = $httpResponse->withAddedHeader('Allow', 'GET, POST');

        $componentContext->replaceHttpResponse($httpResponse);
        $componentContext->setParameter(ComponentChain::class, 'cancel', true);
    }
}
