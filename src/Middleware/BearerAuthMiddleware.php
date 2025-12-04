<?php

namespace App\Middleware;

use App\Security\JwtAuth;
use Exception;
use Firebase\JWT\SignatureInvalidException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpForbiddenException;

final class BearerAuthMiddleware implements MiddlewareInterface
{
    private JwtAuth $jwtAuth;
    public function __construct(JwtAuth $jwtAuth)
    {
        $this->jwtAuth = $jwtAuth;
    }
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        try {
// Get token from "Authorization" header, see .htaccess addition for this to work.
            //die (print_r($_SERVER));
            $jwt = explode(' ', (string)$_SERVER['HTTP_AUTHORIZATION'])[1] ?? '';
            if (!$jwt) {
                throw new SignatureInvalidException('No Bearer Token provided');
            }
            // Validate and decode token
            $payload = $this->jwtAuth->decode($jwt);
// Optional, map payload data to DTO
            $user = (array)($payload->data ?? []);

// Add current user details to request
            $request = $request->withAttribute('user', $user);
            return $handler->handle($request);
} catch (Exception $exception) {
            throw new HttpForbiddenException($request, 'Unauthorized', $exception);
        }
    }
}