<?php

namespace App\Actions\Policy;

use App\Actions\Action;
use App\Repositories\PaymentsRepository;
use App\Repositories\PolicyHolderRepository;
use App\Repositories\ProductsRepository;
use App\Repositories\SerialsRepository;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface as Response;
use App\Domain\Services\SftpService;

#[\AllowDynamicProperties]
class CreatePolicyPayloadAction extends Action
{
    protected Logger $logger;

    protected SerialsRepository $serials;

    protected PolicyHolderRepository $policyHolderRepository;

    protected PaymentsRepository $payments;

    protected ProductsRepository $products;

    protected SftpService $sftpService;

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
        $policyHolderJson = json_decode($policyHolder['entity_object'], true);

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

        //get payment details
        $payment = $this->payments->fetch($id);

        //policy date
        $dt = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');

        //product configuration
        $product_config = json_decode($product['product_configuration'], true);

        //build the payload
        $data = array(
            array(
                "bundle_id" => $product['product_bundle_id'],
                "policy_start_date" => $dt,
                "cover_term" => $product_config['term'],
                "cover_term_unit" => strtoupper($product_config['term_unit']),
                "terms_and_conditions_accepted" => true,
                "popia_consent_given" => true,
                "bundle_multiplier" => 1,
                "bank_details" => array(
                    "bank_account_name" => $policyHolderJson['name'] . ' ' . $policyHolderJson['surname'],
                    "bank_name" => $payment['bank'],
                    "bank_account_number" => $payment['acc_no'],
                    "bank_branch" => 'Main',
                    "account_type" => 'CURRENT'
                ),
                "policyholder_employment" => array(
                    'status' => $policyHolderJson['employment_status'],
                    'industry' => $policyHolderJson['employment_industry'],
                ),
                "products" => array(
                    array(
                        "product_short_code" => "101",
                        "external_product_life_id" => $policyHolder['uuid'],
                        "name" => $policyHolder['name'],
                        "surname" => $policyHolder['surname'],
                        "date_of_birth" => $policyHolder['dob'],
                        "gender" => $policyHolder['gender'],
                        "mobile_number" => '+27' . substr($policyHolder['cellno'], -9),
                        "email_address" => $policyHolder['email'],
                        "relationship_to_main" => $policyHolder['relationship'],
                        "id_type" => !empty($policyHolder['idno'])?$policyHolderJson['id_type']:'',
                        "id_value" => $policyHolder['idno'],
                        "id_expiry_date" => !empty($policyHolder['idno'])?'2030-01-01':'',
                        "id_country_of_issue" => !empty($policyHolder['idno'])?'ZAF':'',
                        "id_issued_by" => !empty($policyHolder['idno'])?'DHA':'',
                        "id_validated_by" => '',
                        "id_validated_when" => '',
                        "beneficiaries" => $beneficiaries_payload
                    )
                ),
            )
        );

        // Convert to JSON
        $jsonData = json_encode($data, JSON_PRETTY_PRINT);

// Define file path
        $file = 'PAYLOAD_' . $serial['serialno'] . '.json';
        $upload = $this->sftpService->processUpload($file, $jsonData);

// Write JSON to file
        if (file_put_contents($file, $jsonData)) {

        } else {
            throw new \Exception("JSON file creation failed.");
        }

        return $this->respondWithData($upload, 200, 'Payload Uploaded');

    }
}