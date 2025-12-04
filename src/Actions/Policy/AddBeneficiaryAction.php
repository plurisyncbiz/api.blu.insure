<?php

namespace App\Actions\Policy;

use App\Actions\Action;
use App\Repositories\ActivationsRepository;
use App\Repositories\SerialsRepository;
use App\Repositories\PolicyHolderRepository;

use Cake\Validation\Validator;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface as Response;
#[\AllowDynamicProperties]
class AddBeneficiaryAction extends Action
{
    protected Logger $logger;

    protected ActivationsRepository $activations;
    protected SerialsRepository $serials;
    protected PolicyHolderRepository $policyHolder;

    public function __construct(Logger $logger, ActivationsRepository $activations, SerialsRepository $serials, PolicyHolderRepository $policyHolder)
    {
        $this->logger = $logger;
        $this->activations = $activations;
        $this->serials = $serials;
        $this->policyHolder = $policyHolder;
    }

    protected function action(): Response
    {
        //get body
        $body = $this->resolveParsedBody();

        $this->serials->changeStatus($body['activationid'], 'INPROGRESS_BENEFICIARY');

        /* VALIDATE VALUES */
        $validator = new Validator();

        $validator
            ->date('date_of_birth', ['ymd'], 'Please enter a valid date of birth.')
            ->requirePresence('date_of_birth', 'create')
            ->add('date_of_birth', 'Over18', [
                'rule' => function ($value, array $context) {
                    if (empty($value)) {
                        return true; // Don't validate age if date is empty, handled by other rules
                    }
                    //create dob
                    $dob = new \DateTime($value);
                    //create current date
                    $now = new \DateTime();

                    $eighteenYearsAgo = $now->modify('-18 years');
                    return $dob <= $eighteenYearsAgo;
                },
                'message' => 'A beneficiary must be at least 18 years old.'
            ])
            ->add('date_of_birth', 'Under60', [
                'rule' => function ($value, array $context) {
                    if (empty($value)) {
                        return true; // Don't validate age if date is empty, handled by other rules
                    }
                    //create dob
                    $dob = new \DateTime($value);
                    //create current date
                    $now = new \DateTime();

                    $sixtyYearsAgo = $now->modify('-60 years');
                    return $dob >= $sixtyYearsAgo;
                },
                'message' => 'A beneficiary must be younger than 60 years old.'
            ]);

        $validator
            ->allowEmptyString('id_value', true)
            ->add('id_value', 'validId', [
                'rule' => function ($value, array $context) {
                    if(!$this->validateSaId($value)){
                        return 'Invalid RSA Id number';
                    }
                    return true;
                },
                'message' => 'Invalid RSA Id number'
            ]);

        //return any validation errors
        $errors = $validator->validate($body);
        $formattedErrors = [];
        foreach ($errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $message) {
                $formattedErrors[] = [
                    'field' => $field,
                    'message' => $message,
                ];
            }
        }
        if($errors){
            $this->serials->changeStatus($body['activationid'], 'ERRORS_BENEFICIARY');
            return $this->respondWithData($formattedErrors, 422, $formattedErrors[0]['message']);
        }

        //get value of
        $id_values = $this->getIDValues($body['id_value']);

        //check if dob and id dob match
        if(isset($body['idno']) && isset($body['date_of_birth']) && $body['date_of_birth']!=$id_values['dob']){
            $this->serials->changeStatus($body['activationid'], 'DOB_ID_MISMATCH_BENEFICIARY');
            return $this->respondWithData(null, 400, 'Date of birth and id number date of birth do not match');
        }

        $values = array(
            $body['name'],
            $body['surname'],
            $body['mobile_number'],
            $body['email_address'],
            $body['id_value'],
            $body['relationship_to_main'],
            $body['activationid'],
            $body['gender'],
            $body['date_of_birth'],
            json_encode($body)
        );

        $data = $this->policyHolder->create($values);
        $this->serials->changeStatus($body['activationid'], 'BENEFICIARY');

        //put in Action
        return $this->respondWithData($data, 200, 'Beneficiary Added');
    }
}