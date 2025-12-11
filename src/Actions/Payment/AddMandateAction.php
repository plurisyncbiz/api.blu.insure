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

    public function __construct(
        Logger $logger,
        SerialsRepository $serials,
        ProductsRepository $products,
        PolicyHolderRepository $policyHolderRepository,
        PaymentsRepository $payments
    ) {
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

        // 1. Build the Mandate Data
        $data = $this->buildMandate($id);

        // -------------------------------------------------------------
        // NEW: Save the mandate JSON to the payments table
        // -------------------------------------------------------------
        // Assuming your PaymentsRepository has an 'updateMandateJson' method
        $this->payments->updateMandateJson($id, json_encode($data));

        // 2. Submit to Mercantile
        $result = json_decode($this->submitMercantileMandate($data), true);

        // 3. Update the activation status
        // NOTE: Hardcoded to use Delayed mandates, not realtime, therefore error is return.

        // Check if 'bank_description' exists and matches success
        $bankDesc = $result['data']['bank_description'] ?? '';
        $isSuccess = ($bankDesc === 'Transaction Successful - Successful Debit or Mandate Accepted');

        if (!$isSuccess) {
            $status = $this->serials->changeStatusJson($id, 'MANDATE_ERROR', json_encode($result));
            return $this->respondWithData($result, 409, $result['description'] ?? 'Mandate Failed');
        } else {
            $status = $this->serials->changeStatusJson($id, 'MANDATE_SUBMITTED', json_encode($result));
            return $this->respondWithData($result, 200, $bankDesc);
        }
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

        // Debtor information
        $debtor_name = $policyHolder['name'] . ' ' . $policyHolder['surname'];
        $debtor_identification = $policyHolder['idno'];
        $debtor_cellno = $policyHolder['cellno'];
        $debtor_accno = $payment['acc_no'];
        $debtor_bank = $payment['bank'];
        $payment_ref = $serial['serialno'];
        $product_price = $serial['product_price'];

        // Date logic
        $currentDate = new \DateTime();
        $newDate = clone $currentDate;
        $newDate->modify('+1 day');

        // Skip weekends
        $dayOfWeek = $newDate->format('N'); // 1 = Monday, 7 = Sunday
        if ($dayOfWeek == 6) { // Saturday
            $newDate->modify('+2 days');
        } elseif ($dayOfWeek == 7) { // Sunday
            $newDate->modify('+1 day');
        }

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

    // ... (rest of your helper functions getUniversalBranchCode, submitMercantileMandate, GetToken remain the same) ...

    private function getUniversalBranchCode($bankName) {
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
        ];

        $bankName = strtoupper(trim($bankName));

        foreach ($bankUBCs as $name => $ubc) {
            if (strtolower($name) === strtolower($bankName)) {
                return $ubc;
            }
        }
        return null;
    }

    private function submitMercantileMandate($data){
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

        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new \RuntimeException("cURL error: " . $error);
        }

        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

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
        $result = json_decode($return);
        return $result;
    }
}