<?php

namespace App\Actions\Payment;

use App\Actions\Action;
use Psr\Http\Message\ResponseInterface as Response;
use App\Repositories\PaymentsRepository;
use App\Repositories\PolicyHolderRepository;
use App\Repositories\ProductsRepository;
use App\Repositories\SerialsRepository;
use Monolog\Logger;
#[\AllowDynamicProperties]
class AddMandateAction extends Action
{
    protected Logger $logger;

    protected SerialsRepository $serials;

    protected PolicyHolderRepository $policyHolderRepository;

    protected PaymentsRepository $payments;

    protected ProductsRepository $products;

    public function __construct(Logger $logger, SerialsRepository $serials, ProductsRepository $products, PolicyHolderRepository $policyHolderRepository, PaymentsRepository $payments)
    {
        $this->logger = $logger;
        $this->serials = $serials;
        $this->products = $products;
        $this->policyHolderRepository = $policyHolderRepository;
        $this->payments = $payments;
        $this->user = 'blmedia';
        $this->password = 'ASJAHIDEksj2993i93292192inkSNKlals929231wQ!';
    }
    protected function action(): Response
    {
        $id = $this->resolveArg('activationid');
        $data = $this->buildMandate($id);

        //submit to mercantile
        $result = json_decode($this->submitMercantileMandate($data), true);
        //update the activation status, must be accepted by remote bank.
        //NOTE: Hardcoded to use Delayed mandates, not realtime, therefore error is return.
        if(!isset($result['data']['bank_description']) && $result['data']['bank_description'] != 'Transaction Successful - Successful Debit or Mandate Accepted'){
            $status = $this->serials->changeStatusJson($id, 'MANDATE_ERROR', json_encode($result));
            return $this->respondWithData($result, 409, $result['description']);
        } else {
            $status = $this->serials->changeStatusJson($id, 'MANDATE_SUBMITTED', json_encode($result));
            return $this->respondWithData($result, 200, $result['data']['bank_description']);

        }
        //put in Action
    }

    /**
     * @param int $id activationid
     * @return array
     */
    private function buildMandate(int $id): array
    {
        $payment = $this->payments->fetch($id);
        $policyHolder = $this->policyHolderRepository->getMainLifeById($id);
        $serial = $this->serials->findByActivation($id);

        //debtor information
        $debtor_name = $policyHolder['name'] . ' ' . $policyHolder['surname'];
        $debtor_identification = $policyHolder['idno'];
        $debtor_cellno = $policyHolder['cellno'];
        $debtor_accno = $payment['acc_no'];
        $debtor_bank = $payment['bank'];
        $payment_ref = $serial['serialno'];
        $product_price = $serial['product_price'];


        // Current date
        $currentDate = new \DateTime();

// Copy current date to new variable
        $newDate = clone $currentDate;

// Add 1 day
        $newDate->modify('+1 day');

// Skip weekends if new date lands on Saturday or Sunday
        $dayOfWeek = $newDate->format('N'); // 1 = Monday, 7 = Sunday
        if ($dayOfWeek == 6) { // Saturday
            $newDate->modify('+2 days');
        } elseif ($dayOfWeek == 7) { // Sunday
            $newDate->modify('+1 day');
        }

        //check if activation is valid

        $array = [
            "user_reference" => $payment_ref,
            "contract_reference" => $payment_ref,
            "tracking_indicator" => "Y",
            "debtor_authentication_code" => "0227",
            "installment_occurence" => "OOFF",
            "frequency" => "YEAR",
            "mandate_initiation_date" => $currentDate->format('Y-m-d'),
            "first_collection_date" => "YEAR",
            "collection_amount" => $product_price,
            "maximum_collection_amount" => $product_price,
            "entry_class" => "0021",
            "debtor_account_name" => $debtor_name,
            "debtor_identification" => $debtor_identification,
            "debtor_account_number" => $debtor_accno,
            "debtor_account_type" => "CURRENT",
            "debtor_branch" => $this->getUniversalBranchCode(strtoupper($debtor_bank)),
            "cellno" => $debtor_cellno,
            "email" => "",
            "collection_day" => $newDate->format('d'),
            "date_adjusment_rule" => "Y",
            "adjustment_category" => "A",
            "adjustment_rate" => "0",
            "adjustment_amount" => "0.00",
            "first_collection_amount" => "0.00",
            "debit_value_type" => "FIXED",
            "user_code" => "OWTH",
            "auto_rms_value" => "Y",
            "devenv" => "live"
        ];

        return $array;
    }

    private function getUniversalBranchCode($bankName) {
        // Mapping of bank names to universal branch codes
        $bankUBCs = [
            'ABSA' => '632005',
            'CAPITEC' => '470010',
            'FNB' => '250655',
            'NEDBANK' => '198765',
            'SBSA' => '051001',
            'ACCESSBANK' => '410506',
            'AFRICANBANK' => '483845',
            'TYMEBANK' => '978765',
            'FINBOND' => '589000',
            // Add more banks as needed
        ];

        // Normalize the input for case-insensitive matching
        $bankName = strtoupper(trim($bankName));

        foreach ($bankUBCs as $name => $ubc) {
            if (strtolower($name) === strtolower($bankName)) {
                return $ubc;
            }
        }

        // Return null if bank not found
        return null;
    }

    private function submitMercantileMandate($data){
// Encode JSON safely
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        $bearerToken = $this->getToken()->token;
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://blt-api.blds-leads.com/v1/uccs/mercantile/mandate/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $bearerToken,
            ],
        ]);

        $response = curl_exec($curl);

        // cURL error handling
        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new \RuntimeException("cURL error: " . $error);
        }

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        // Optional: handle non-200 responses
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException("API responded with HTTP $status: $response");
        }

        return $response;
    }

    private function GetToken() {
        $ch = curl_init( 'https://blt-api.blds-leads.com/v1/token');
        curl_setopt($ch, CURLOPT_USERPWD, $this->user . ":" . $this->password);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $return = curl_exec($ch);
        curl_close($ch);
        //decode result
        $result = json_decode($return);
        //store the session
        return $result;
    }

}