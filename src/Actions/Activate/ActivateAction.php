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
        // 1. Get the Serial Number from the Body
        $body = $this->resolveParsedBody();
        $serialno = $body['serialno'] ?? null;

        if(!$serialno){
            return $this->respondWithData([], 400, 'Serial number required');
        }

        // 2. VALIDATION: Fetch the row from the DB
        // This returns the array structure you showed in your prompt
        $serialData = $this->serials->findBySerial($serialno);

        // Check if the array is empty (Serial not found)
        if (!$serialData || count($serialData) === 0) {
            return $this->respondWithData([], 404, 'Serial number not found');
        }

        // 3. EXTRACT PRODUCT DETAILS
        // The fetchAll() returns an array of rows, so we target the first row [0]
        $row = $serialData[0];

        // We extract the specific columns you showed in your result
        $productDetails = [
            'product_name'        => $row['product_name'],        // "Sanlam Prepaid Funeral Cover"
            'product_description' => $row['product_description'], // "3 Months"
            'product_code'        => $row['product_code'],        // "10001"
            'price'               => $row['product_price']        // "125.00"
        ];

        // 4. Check if already activated
        // (We use the 'activationid' column from your result to save a DB call)
        if(!empty($row['activationid']) || $row['current_status'] === 'ACTIVATED'){
            // We still return the product details so the USSD can say "You ALREADY have [Product Name]"
            return $this->respondWithData([$productDetails], 409, 'Serial is already activated');
        }

        $data = array(
            $serialno,
            $body['ip_address'] ?? '127.0.0.1',
            $body['user_agent'] ?? 'API'
        );

        // 5. Create the activation record
        $activationid = $this->activations->create($data);

        // 6. Update the serial status
        $this->serials->updateActivation($serialno, $activationid);

        // 7. MERGE AND RETURN
        // We combine the activation ID with the product details we extracted earlier
        $responsePayload = array_merge(
            ['activation_id' => $activationid],
            $productDetails
        );

        return $this->respondWithData([$responsePayload], 200, 'Activation successful');
    }
}