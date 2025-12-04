<?php

namespace App\Actions;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Monolog\Logger;
use Doctrine\DBAL\Connection;
use App\Renderer\JsonRenderer;

#[\AllowDynamicProperties]
class HomeAction extends Action
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }
    protected function action(): Response
    {
        //put in Action
        $this->logger->info('Main index accessed');
        return  $this->respondWithData('Welcome to the API', 200, 'Welcome to the API');
    }
}