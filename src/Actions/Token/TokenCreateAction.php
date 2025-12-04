<?php

namespace App\Actions\Token;


use App\Security\JwtAuth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpUnauthorizedException;

final class TokenCreateAction
{
    private JwtAuth $jwtAuth;
    public function __construct(JwtAuth $jwtAuth)
    {
        $this->jwtAuth = $jwtAuth;
    }
    public function __invoke(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
// Get username and password from request
        $values = $request->getParsedBody();
        $username = $values['username'];
        $password = $values['password'];
        //die(print_r($values));
// Performs an authentication attempt
// !!! Pseudo example !!!
        $userId = null;
        if ($username === 'user' && $password === 'secret') {
            $userId = 1;
        }
        if (!$userId) {
            throw new HttpUnauthorizedException($request);
        }
        $data = [
            'id' => $userId,
        ];
        $bearer = $this->jwtAuth->encodeBearer($data);
        $json = json_encode($bearer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $response->getBody()->write($json);
        return $response
            ->withStatus($response->getStatusCode())
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Pragma', 'no-cache');
}
}