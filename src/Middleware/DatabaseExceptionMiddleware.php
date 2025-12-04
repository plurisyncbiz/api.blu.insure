<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DatabaseExceptionMiddleware implements MiddlewareInterface
{
    private $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }
    /**
     * @inheritDoc
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        try {
            return $handler->handle($request);
        } catch (PDOException $exception) {
            // Transform exception to JSON
            $result = [
                'StatusCode' => 500,
                'error' => [
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage(),
                ]
            ];

            $response = $this->responseFactory->createResponse(500);
            $response->getBody()->write((string)json_encode($result));

            return $response;
        }
    }
}