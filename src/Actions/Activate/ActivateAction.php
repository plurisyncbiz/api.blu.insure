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
        $body = $this->resolveParsedBody();
        $serialno = $body['serialno'] ?? null;

        // 1. MAKE CELL NUMBER OPTIONAL (Backward Compatibility)
        // If the remote side sends it, we check it. If not, we skip the check.
        $incomingCell = $body['cellno'] ?? null;

        if(!$serialno){
            return $this->respondWithData([], 400, 'Serial number required');
        }

        // 2. FETCH SERIAL DATA
        $serialData = $this->serials->findBySerial($serialno);

        if (!$serialData || count($serialData) === 0) {
            return $this->respondWithData([], 404, 'Serial number not found');
        }

        $row = $serialData[0];

        // 3. SECURITY CHECK (Conditional)
        $storedCell = trim($row['cellno'] ?? '');

        // We only perform the check if BOTH the DB has a locked number AND the request provided a number.
        if (!empty($storedCell) && !empty($incomingCell)) {
            if ($storedCell !== trim($incomingCell)) {
                // FAIL: The voucher belongs to a different number
                return $this->respondWithData([], 403, 'This voucher belongs to a different mobile number.');
            }
        }

        // 4. PREPARE PRODUCT DETAILS
        $config = json_decode($row['product_configuration'] ?? '{}', true);
        $coverAmount = $config['cover'] ?? '0.00';

        $productDetails = [
            'product_name'        => $row['product_name'],
            'product_description' => $row['product_description'],
            'product_code'        => $row['product_code'],
            'price'               => $row['product_price'],
            'cover_amount'        => $coverAmount,
            'serial_cellno'       => $storedCell
        ];

        $status = $row['current_status'] ?? 'UNKNOWN';

        // 5. CHECK IF ALREADY ACTIVATED
        // We return 409 but include the product details so the UI can proceed to "Policy Details"
        if(!empty($row['activationid']) || $row['current_status'] === 'ACTIVATED'){
            $payload = array_merge(
                ['activation_id' => $row['activationid']],
                ['serial_current_status' => $status],
                $productDetails
            );
            return $this->respondWithData([$payload], 409, 'Serial is already activated. Proceed to policy details.');
        }

        // 6. ACTIVATE
        $data = array(
            $serialno,
            $body['ip_address'] ?? '127.0.0.1',
            $body['user_agent'] ?? 'API'
        );

        $activationid = $this->activations->create($data);
        $this->serials->updateActivation($serialno, $activationid);

        // 7. SUCCESS RESPONSE
        $payload = array_merge(
            ['activation_id' => $activationid],
            $productDetails
        );

        return $this->respondWithData([$payload], 200, 'Activation successful');
    }
}