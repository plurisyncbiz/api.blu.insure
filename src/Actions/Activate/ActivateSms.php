<?php

namespace App\Actions\Activate;

use App\Actions\Action;
use App\Repositories\ActivationsRepository;
use App\Repositories\SerialsRepository;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface as Response;

#[\AllowDynamicProperties]
class ActivateSms extends Action
{
    protected Logger $logger;

    protected ActivationsRepository $activations;
    protected SerialsRepository $serials;
    public function __construct(Logger $logger, ActivationsRepository $activations, SerialsRepository $serials)
    {
        $this->logger = $logger;
        $this->activations = $activations;
        $this->serials = $serials;
    }
    protected function action(): Response
    {
        //get activations to SMS
        $sms = $this->activations->fetchSMS();

        foreach ($sms as $row){
            $cellno = $row['cellno'];
            $url = $_ENV['SMS_POLICY_URL'] . $row['uniqid'];

            //build message, depends on how they came in
            if($type = 1){
                $message = <<<eof
blu.insure: 
Prepaid Sanlam Funeral Cover
Family Package
(Main , Spouse & upto 5 kids)
3 Month Term
R20k cover
Complete Policy Info @ $url
NoDataCosts
eof;
            } elseif ($type == 2){
                $message = <<<eof
blu.insure: 
Prepaid Sanlam Funeral Cover
Family Package
(Main , Spouse & upto 5 kids)
3 Month Term
R20k cover
Complete Policy Info @ $url
NoDataCosts
eof;
            } else {

            }

        }

        return $this->respondWithData($sms, 200);
    }
}