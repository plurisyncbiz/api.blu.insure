<?php

namespace App\Actions\User;

use App\Actions\Action;
use App\Repositories\UserRepository;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface as Response;

#[\AllowDynamicProperties]
final class UserAction extends Action
{

    protected Logger $logger;

    protected UserRepository $users;

    public function __construct(Logger $logger, UserRepository $users)
    {
        $this->logger = $logger;
        $this->users = $users;
    }

    public function action(): Response
    {

        //find user
        $rows = $this->users->findAll();

        //log action
        $this->logger->info('data: ' . json_encode($rows) . ' viewed');

        //put in Action
        return $this->respondWithData($rows);
    }
}