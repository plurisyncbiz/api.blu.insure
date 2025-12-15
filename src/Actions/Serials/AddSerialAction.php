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
        //find user
        $body = $this->resolveParsedBody();


        $validator = new Validator();

        //check cell number for matted correctly
        $validator
            ->requirePresence('cellno', true, 'This field is required')
            ->notEmptyString('cellno', 'cellno is required')
            ->maxLength('cellno', 10, 'cellno is to long')
            ->minLength('cellno', 10, 'cellno is to short')
            ->regex('cellno', '/^0(6[0123456789][0-9]{7}|7[1234689][0-9]{7}|8[12345][0-9]{7})/', 'This must be a valid RSA Cellphone Number in local format')
        ;

        $validator
            ->requirePresence('channel', true, 'This field is required')
            ->notEmptyString('channel', 'This field is required')
            ->inList('channel', array('USSD', 'WHATSAPP', 'AGENT', 'ADTECH'));

        $validator
            ->requirePresence('sales_agent', true, 'This field is required')
            ->notEmptyString('channel', 'This field is required');

        $errors = $validator->validate($body);
        if($errors){
            return $this->respondWithData(array('errors' => $errors), 400, 'There were validation errors');
        }

        //build payload
        $data = array(
            $body['product_code'],
            $body['cellno'],
            $body['channel'],
            $body['sales_agent'],
        );

        //insert records
        $rows = $this->serials->addSerial($data);
        $product = $this->productsRepository->fetchById($body['product_code']);
        $config = json_decode($product['product_configuration'], true);

        //get values
        $cover = $config['cover'];
        $price = $product['product_price'];
        $term = $config['term'];

        //format values
        $cover = $this->shortNumber($cover);

        // Check if the key exists and is NOT null
        if (!isset($rows['uniqid'])) {
            throw new \InvalidArgumentException("Error: 'uniqid' is missing!");
        }

        //get the unique id
        $uniqid = $rows['uniqid'];
        $url = $_ENV['SMS_ACTIVATE_URL'] . '/' . $uniqid;
        //construct the invite SMS
        $message = "Activate your $cover Sanlam Prepaid Funeral Cover: $url (data free). R$price covers you for $term months. FSP11230 . Need Help? call us on 087 330 5365";
        //send the sms
        $this->sms->processSms($body['cellno'], $message, $uniqid);
        //put in Action
        return $this->respondWithData($config, 200, 'Serial added');
    }

    private function shortNumber($num, $precision = 1) {
        if ($num < 1000) {
            return $num;
        }

        // Define the suffixes
        $suffixes = ['', 'k', 'M', 'B', 'T'];

        // Calculate which suffix to use
        $suffixIndex = floor(log($num, 1000));

        // Divide the number by the power of 1000
        $number = $num / pow(1000, $suffixIndex);

        // Round it and add the suffix
        return round($number, $precision) . $suffixes[$suffixIndex];
    }
}