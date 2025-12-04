<?php

namespace App\Actions\Activate;

use App\Actions\Action;
use App\Repositories\ActivationsRepository;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface as Response;
use App\Repositories\SerialsRepository;
use Cake\Validation\Validator;
use Tuupola\Base62;

#[\AllowDynamicProperties]
class ActivateAction extends Action
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
        //get the unique id.
        $body = $this->resolveParsedBody();

        //check if already activated
        $activation = $this->activations->fetchBySerial($body['serialno']);
        if(count($activation) >= 1){
            return $this->respondWithData(array(), 404, 'Activation exists');
        }

        $data = array(
            $body['serialno'],
            $body['ip_address'],
            $body['user_agent']
        );

        //activate the
        $activationid = $this->activations->create($data);

        $data = $this->serials->updateActivation($body['serialno'], $activationid);

        //put in Action
        return $this->respondWithData(array($data), 200, 'Activation ID updated');

    }
}