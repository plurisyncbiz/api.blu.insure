<?php

namespace App\Actions\Policy;

use App\Actions\Action;
use App\Repositories\ActivationsRepository;
use App\Repositories\PaymentsRepository;
use App\Repositories\SerialsRepository;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface as Response;

#[\AllowDynamicProperties]
class AddPaymentAction extends Action
{
    protected Logger $logger;
    protected ActivationsRepository $activations;
    protected SerialsRepository $serials;
    protected PaymentsRepository $payments;

    public function __construct(Logger $logger, ActivationsRepository $activations, SerialsRepository $serials, PaymentsRepository $payments)
    {
        $this->logger = $logger;
        $this->activations = $activations;
        $this->serials = $serials;
        $this->payments = $payments;
    }

    protected function action(): Response
    {
        $body = $this->resolveParsedBody();
        $activationId = $body['activationid'];

        $this->serials->changeStatus($activationId, 'INPROGRESS_PAYMENT');

        // 1. Check if payment already exists
        $existingPayment = $this->payments->fetch($activationId);

        if ($existingPayment) {
            // --- UPDATE EXISTING ---
            // Note: The order matches the UPDATE query in the repository:
            // acc_no, bank, branch, date, json_entity, WHERE activationid
            $values = [
                $body['acc_no'],
                $body['bank'],
                $body['branch_code'],
                $body['debit_date'],
                json_encode($body),
                $activationId // Goes last for the WHERE clause
            ];

            $this->payments->update($values);
            $message = 'Payment Updated';
            $data = ['status' => 'success', 'id' => $existingPayment['id'] ?? null]; // Keep existing ID

        } else {
            // --- CREATE NEW ---
            // Note: The order matches the INSERT query in the repository:
            // acc_no, bank, branch, date, activationid, json_entity
            $values = [
                $body['acc_no'],
                $body['bank'],
                $body['branch_code'],
                $body['debit_date'],
                $activationId,
                json_encode($body)
            ];

            $data = $this->payments->create($values);
            $message = 'Payment Added';
        }

        $this->serials->changeStatus($activationId, 'PAYMENT');

        return $this->respondWithData($data, 200, $message);
    }
}