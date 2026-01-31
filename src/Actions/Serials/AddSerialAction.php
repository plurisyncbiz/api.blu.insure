<?php

namespace App\Actions\Serials;

use App\Actions\Action;
use App\Repositories\ProductsRepository;
use App\Repositories\SerialsRepository;
use Cake\Validation\Validator;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface as Response;
use App\Domain\Services\SmsService;

#[\AllowDynamicProperties]
final class AddSerialAction extends Action
{
    protected Logger $logger;
    protected SerialsRepository $serials;
    protected SmsService $sms;
    protected ProductsRepository $productsRepository;

    public function __construct(Logger $logger, SerialsRepository $serials, SmsService $sms, ProductsRepository $productsRepository)
    {
        $this->logger = $logger;
        $this->serials = $serials;
        $this->sms = $sms;
        $this->productsRepository = $productsRepository;
    }

    protected function action(): Response
    {
        $body = $this->resolveParsedBody();

        // 1. Validation
        $validator = new Validator();

        $validator
            ->requirePresence('cellno', true, 'This field is required')
            ->notEmptyString('cellno', 'cellno is required')
            ->maxLength('cellno', 10, 'cellno is too long')
            ->minLength('cellno', 10, 'cellno is too short')
            ->regex('cellno', '/^0(6[0123456789][0-9]{7}|7[1234689][0-9]{7}|8[12345][0-9]{7})/', 'This must be a valid RSA Cellphone Number in local format');

        $validator
            ->requirePresence('channel', true, 'This field is required')
            ->notEmptyString('channel', 'This field is required')
            ->inList('channel', array('USSD', 'WHATSAPP', 'AGENT', 'ADTECH'));

        $validator
            ->requirePresence('sales_agent', true, 'This field is required')
            ->notEmptyString('sales_agent', 'This field is required');

        $errors = $validator->validate($body);
        if($errors){
            return $this->respondWithData(array('errors' => $errors), 400, 'There were validation errors');
        }

        // 2. Prepare Data for Repository
        $data = array(
            $body['product_code'],
            $body['cellno'],
            $body['channel'],
            $body['sales_agent'],
        );

        // 3. Insert Record
        // Returns ['id' => 1, 'uniqid' => 'abc', 'serialno' => '110001']
        $rows = $this->serials->addSerial($data);

        // 4. Fetch Product Details
        $product = $this->productsRepository->fetchById($body['product_code']);
        $config = json_decode($product['product_configuration'], true);

        // Format Values
        $cover = $this->shortNumber($config['cover']);
        $price = $product['product_price'];
        $term = $config['term'];

        // Safety Checks
        if (!isset($rows['uniqid'])) {
            throw new \InvalidArgumentException("Error: 'uniqid' is missing from repository return!");
        }
        if (!isset($rows['serialno'])) {
            throw new \InvalidArgumentException("Error: 'serialno' is missing from repository return!");
        }

        // 5. Send SMS
        $uniqid = $rows['uniqid'];
        $serialno = $rows['serialno'];
        $url = $_ENV['SMS_ACTIVATE_URL'] . '/' . $uniqid;
        $ussd = $_ENV['SMS_POLICY_USSD'];

        $message = "Thanks for your interest in $cover Sanlam Prepaid Funeral Cover. Your serial number is: $serialno. Activate your policy via USSD dial $ussd --or-- ONLINE click $url. Pay R$price for $term months of cover. SDM Life Licensed Insurer & Auth FSP11230.";

        $this->sms->processSms($body['cellno'], $message, $uniqid);

        // 6. Build Final Response
        // We add the created serial number to the config array
        $responseData = array_merge($config, [
            'serialno' => $rows['serialno'], // This is what you needed
            'uniqid'   => $uniqid
        ]);

        return $this->respondWithData($responseData, 200, 'Serial added');
    }

    private function shortNumber($num, $precision = 1) {
        if ($num < 1000) {
            return $num;
        }
        $suffixes = ['', 'k', 'M', 'B', 'T'];
        $suffixIndex = floor(log($num, 1000));
        $number = $num / pow(1000, $suffixIndex);
        return round($number, $precision) . $suffixes[$suffixIndex];
    }
}