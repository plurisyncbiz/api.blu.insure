<?php

namespace App\Actions\Policy;

use App\Actions\Action;
use App\Domain\Services\SftpService;
use App\Repositories\PaymentsRepository;
use App\Repositories\ProductsRepository;
use App\Repositories\SerialsRepository;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface as Response;
use App\Repositories\PolicyHolderRepository;
#[\AllowDynamicProperties]
class GetPolicyInfoAction extends Action
{
    public function __construct(Logger $logger, SerialsRepository $serials, ProductsRepository $products, PolicyHolderRepository $policyHolderRepository, PaymentsRepository $payments, SftpService $sftpService)
    {
        $this->logger = $logger;
        $this->serials = $serials;
        $this->products = $products;
        $this->policyHolderRepository = $policyHolderRepository;
        $this->payments = $payments;
        $this->sftpService = $sftpService;
    }

    protected function action(): Response
    {
        $id = $this->resolveArg('id');

        //get serial details
        $serial = $this->serials->findByActivation($id);
        if(!$serial){
            return $this->respondWithData($serial, 404, 'Serial Not Found');
        }
        //get product
        $product = $this->products->fetchById($serial['product_code']);

        //get main life
        $policyHolder = $this->policyHolderRepository->getMainLifeById($id);
        $policyHolderEntity = json_decode($policyHolder['entity_object'], true);

        //get beneficiaries
        $beneficiaries = $this->policyHolderRepository->getBeneficiariesById($id);
        $beneficiaryCount = count($beneficiaries);
        if($beneficiaryCount===2){
            $percentage_allocation = '50';
        } elseif($beneficiaryCount === 1) {
            $percentage_allocation = '100';
        }

        $beneficiaries_payload = array();
        foreach ($beneficiaries as $beneficiary) {
            $entity = json_decode($beneficiary['entity_object'], true);
            $beneficiaries_payload[] = array(
                "percentage_allocation" => $percentage_allocation,
                "name" => $beneficiary['name'],
                "surname" => $beneficiary['surname'],
                "date_of_birth" => $beneficiary['dob'],
                "gender" => $beneficiary['gender'],
                "mobile_number" => '+27' . substr($beneficiary['cellno'], -9),
                "email_address" => $beneficiary['email'],
                "id_type" => !empty($beneficiary['idno'])?$entity['id_type']:'',
                "id_value" => $beneficiary['idno'],
                "id_expiry_date" => !empty($beneficiary['idno'])?'2030-01-01':'',
                "id_country_of_issue" => !empty($beneficiary['idno'])?'ZAF':'',
                "id_issued_by" => !empty($beneficiary['idno'])?'DHA':'',
                "id_validated_by" => '',
                "id_validated_when" => '',

            );
        }


        //product configuration
        $product_config = json_decode($product['product_configuration'], true);

        //build the payload
        $data = array(
                "beneficiaries" => $beneficiaries_payload,
                "product_config" => $product_config,
                "policy_holder" => $policyHolderEntity
            );

        // Convert to JSON
        return $this->respondWithData($data, 200, 'Created');

    }
}