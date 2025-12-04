<?php

namespace App\Actions\Policy;

use App\Actions\Action;
use App\Repositories\ActivationsRepository;
use App\Repositories\PaymentsRepository;
use App\Repositories\SerialsRepository;
use App\Repositories\PolicyHolderRepository;

use Cake\Validation\Validator;
use Monolog\Logger;
use mysql_xdevapi\Exception;
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
        //get body
        $body = $this->resolveParsedBody();

        $this->serials->changeStatus($body['activationid'], 'INPROGRESS_PAYMENT');

        $values = array(
            $body['acc_no'],
            $body['bank'],
            $body['branch_code'],
            $body['debit_date'],
            $body['activationid'],
            json_encode($body)
        );

        //create the record
        $data = $this->payments->create($values);
        $this->serials->changeStatus($body['activationid'], 'PAYMENT');

        //process the debit order
        $paymentItem = array(
            'user_reference' => '',
            'contract_reference' => '',
            'tracking_indicator' => 'Y',
            'debtor_authentication_code' => '0227',
            'installment_occurence' => 'OOFF',
            'frequency' => 'MNTH',
            'mandate_initiation_date' => '',
            'first_collection_date' => '',
            'collection_amount' => '',
            'maximum_collection_amount' => '',
            'entry_class' => '0035',
            'debtor_account_name' => '',
            'debtor_identification' => '',
            'debtor_account_number' => '',
            'debtor_account_type' => '',
            'debtor_branch' => '',
            'cellno' => '',
            'email' => '',
            'collection_day' => '',
            'date_adjusment_rule' => 'Y',
            'adjustment_category' => 'N',
            'adjustment_amount' => '',
            'first_collection_amount' => '125.00',
            'debit_value_type' => 'FIXED',
            'user_code' => 'OWTH',
            'devenv' => 'live'
        );



        //put in Action
        return $this->respondWithData($data, 200, 'Payment Added');
    }
}