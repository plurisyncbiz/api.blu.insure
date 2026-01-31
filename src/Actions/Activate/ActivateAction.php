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
        // 1. Get the Serial Number
        $body = $this->resolveParsedBody();
        $serialno = $body['serialno'] ?? null;

        if(!$serialno){
            return $this->respondWithData([], 400, 'Serial number required');
        }

        // 2. Fetch the row from the DB
        $serialData = $this->serials->findBySerial($serialno);

        // Check if Serial exists
        if (!$serialData || count($serialData) === 0) {
            return $this->respondWithData([], 404, 'Serial number not found');
        }

        $row = $serialData[0];

        // 3. DECODE CONFIGURATION (To get the Cover Amount)
        // The DB returns: "{""term"": 3, ""cover"": 20000...}"
        $config = json_decode($row['product_configuration'] ?? '{}', true);
        $coverAmount = $config['cover'] ?? '0.00'; // Default to 0.00 if missing

        // 4. PREPARE PRODUCT DETAILS
        $productDetails = [
            'product_name'        => $row['product_name'],        // "Sanlam Prepaid Funeral Cover"
            'product_description' => $row['product_description'], // "3 Months"
            'product_code'        => $row['product_code'],        // "10001"
            'price'               => $row['product_price'],       // "125.00"
            'cover_amount'        => $coverAmount                 // "20000" (Extracted from JSON)
        ];

        // 5. Check if already activated
        // We check the 'activationid' field from the fetch result directly
        if(!empty($row['activationid']) || $row['current_status'] === 'ACTIVATED'){
            return $this->respondWithData([$productDetails], 409, 'Serial is already activated');
        }

        // 6. Proceed with Activation
        $data = array(
            $serialno,
            $body['ip_address'] ?? '127.0.0.1',
            $body['user_agent'] ?? 'API'
        );

        $activationid = $this->activations->create($data);
        $this->serials->updateActivation($serialno, $activationid);

        // 7. RETURN COMBINED RESPONSE
        $responsePayload = array_merge(
            ['activation_id' => $activationid],
            $productDetails
        );

        return $this->respondWithData([$responsePayload], 200, 'Activation successful');
    }
}