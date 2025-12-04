<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpException;
use Throwable;

class ExceptionMiddleware implements MiddlewareInterface
{
    private ResponseFactoryInterface $responseFactory;
    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }
    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            $response = $this->responseFactory->createResponse();
            $data = [
                'type' => 'error',
                'description' => $exception->getMessage(),
            ];
            $response->getBody()->write(
                (string)json_encode(
                    $data,
                    JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
                )
            );
// HTTP status code
            $status = 500;
            if ($exception instanceof HttpException) {
                $status = $exception->getCode();
            }
            return $response
                ->withStatus($status)
                ->withHeader('Content-Type', 'application/json');
        }
    }
}