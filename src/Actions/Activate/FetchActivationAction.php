<?php

namespace App\Actions\Activate;

use App\Actions\Action;
use App\Repositories\ActivationsRepository;
use App\Repositories\SerialsRepository;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface as Response;
#[\AllowDynamicProperties]
class FetchActivationAction extends Action
{
    protected Logger $logger;

    protected ActivationsRepository $activations;
    protected SerialsRepository $serials;

    public function __construct(Logger $logger, ActivationsRepository $activations, SerialsRepository $serials)
    {
        $this->logger = $logger;
        $this->activations = $activations;
        $this->serials = $serials;
    }

    protected function action(): Response
    {
        $id = $this->resolveArg('id');

        $result = $this->activations->fetch($id);

        //populate & return
        $data = array(
            'uuid' => '',
            'activationid' => '',
            'serialno' => '',
            'sales_agent' => '',
            'cellno' => '',
            'idno' => '',
            'uniqid' => '',
            'uuid' => '',
            'uuid' => '',
            'uuid' => '',
            'uuid' => '',
        );

        return $this->respondWithData($result);
    }
}