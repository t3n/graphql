<?php

declare(strict_types=1);

namespace t3n\GraphQL\Http;

use GuzzleHttp\Psr7\Response;
use Neos\Flow\Annotations as Flow;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HttpOptionsMiddleware implements MiddlewareInterface
{
    /**
     * @Flow\InjectConfiguration("endpoints")
     *
     * @var mixed[]
     */
    protected $endpoints;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() !== 'OPTIONS') {
            return $handler->handle($request);
        }

        // We explode the request target because custom routes like /some/custom/route/<endpoint> are
        // are common. So we double check here if the last part in the route matches a configured
        // endpoint
        $endpoint = explode('/', ltrim($request->getRequestTarget(), '\/'));

        if (! isset($this->endpoints[end($endpoint)])) {
            return $handler->handle($request);
        }

        return new Response(200, ['Content-Type' => 'application/json', 'Allow' => 'GET, POST'], json_encode(['success' => true]));
    }
}
