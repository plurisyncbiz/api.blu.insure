<?php

namespace App\Actions\Serials;

use App\Actions\Action;
use App\Repositories\SerialsRepository;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface as Response;

#[\AllowDynamicProperties]
class UpdateSerialStatusAction extends Action
{
    protected Logger $logger;
    protected SerialsRepository $serials;

    public function __construct(Logger $logger, SerialsRepository $serials)
    {
        parent::__construct($logger); // Pass logger to parent if required by your base Action
        $this->logger = $logger;
        $this->serials = $serials;
    }

    protected function action(): Response
    {
        // 1. Retrieve the JSON payload from the request body
        $data = $this->request->getParsedBody();

        $uniqid = $data['uniqid'] ?? null;
        $status = $data['status'] ?? null;

        // 2. Validate Input
        if (empty($uniqid) || empty($status)) {
            return $this->respondWithData(['error' => 'Missing uniqid or status'], 400);
        }

        // 3. Resolve UniqID to ActivationID
        // Your repository's changeStatus() requires an activationid, but the frontend sends a uniqid.
        // We use findByUniqid to bridge this gap.
        $serialRecord = $this->serials->findByUniqid($uniqid);

        if (empty($serialRecord) || !isset($serialRecord[0]['activationid'])) {
            return $this->respondWithData(['error' => 'Serial not found or not activated'], 404);
        }

        $activationid = $serialRecord[0]['activationid'];

        // 4. Consume the changeStatus function as requested
        $result = $this->serials->changeStatus($activationid, $status);

        // 5. Return standardized response
        return $this->respondWithData($result, 200, 'Status Updated Successfully');
    }
}