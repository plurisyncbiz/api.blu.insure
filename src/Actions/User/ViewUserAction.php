<?php

namespace App\Actions\User;

use App\Actions\Action;
use App\Repositories\UserRepository;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface as Response;

#[\AllowDynamicProperties]
final class ViewUserAction extends Action
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
        $id = $this->resolveArg('id');
        //get user id

        //find user
        $rows = $this->users->findUserOfId($id);

        //log action
        $this->logger->info('Userid: ' . $id . ' viewed');

        //put in Action
        return $this->respondWithData($rows);
    }
}