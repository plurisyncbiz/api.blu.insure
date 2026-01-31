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
        // 1. Get the unique id.
        $body = $this->resolveParsedBody();
        $serialno = $body['serialno'];

        // 2. VALIDATION: Check if serial exists in the DB first
        // We assume you add the 'fetch' method to your serials class (code below)
        $serialData = $this->serials->findBySerial($serialno);

        if (!$serialData || count($serialData) === 0) {
            // Stop here if the serial doesn't exist in your inventory
            return $this->respondWithData(array(), 404, 'Serial number not found');
        }

        // 3. Capture the Product Name for the response
        // Assuming the column in your DB is named 'product_name'
        $productName = $serialData[0]['product_name'] ?? 'Prepaid Product';

        // 4. Check if already activated
        $activation = $this->activations->fetchBySerial($serialno);
        if(count($activation) >= 1){
            // Changed to 409 (Conflict) as it's more accurate than 404, but 404 works too
            return $this->respondWithData(array(), 409, 'Serial is already activated');
        }

        $data = array(
            $serialno,
            $body['ip_address'],
            $body['user_agent']
        );

        // 5. Create the activation record
        $activationid = $this->activations->create($data);

        // 6. Update the serial status
        $updateResult = $this->serials->updateActivation($serialno, $activationid);

        // 7. BUILD RESPONSE
        // We merge the update result with the product name we found earlier
        $responsePayload = array_merge($updateResult, ['product_name' => $productName]);

        return $this->respondWithData(array($responsePayload), 200, 'Activation successful');
    }
}