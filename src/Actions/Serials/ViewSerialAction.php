<?php

namespace App\Actions\Serials;

use App\Actions\Action;
use App\Repositories\SerialsRepository;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface as Response;
#[\AllowDynamicProperties]
class ViewSerialAction extends Action
{
    protected Logger $logger;

    protected SerialsRepository $serials;

    public function __construct(Logger $logger, SerialsRepository $serials)
    {
        $this->logger = $logger;
        $this->serials = $serials;
    }

    protected function action(): Response
    {
        //get id
        $id = $this->resolveArg('serialno');

        //find user
        $rows = $this->serials->findByUniqid($id);

        //log action
        $this->logger->info('data: ' . json_encode($rows) . ' viewed');

        //put in Action
        return $this->respondWithData($rows, 200, 'Serial Found');
    }
}