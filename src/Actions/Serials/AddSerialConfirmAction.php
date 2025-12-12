<?php

namespace App\Actions\Serials;

use App\Actions\Action;
use App\Repositories\SerialsRepository;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface as Response;
#[\AllowDynamicProperties]

class AddSerialConfirmAction extends Action
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
        $id = $this->resolveArg('activationid');
        $this->serials->markAsConfirmed($id);
        return $this->respondWithData([], 200, 'Confirmed');
    }
}