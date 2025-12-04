<?php

namespace App\Validation;

use Cake\Validation\Validation;

class CustomRuleValidator extends Validation
{
    public function __construct()
    {
        parent::__construct();
    }
    public function ValidIdentityNumber($value, array $context) : Validation
    {
        $id = $value;
        $error = false;
        $match = preg_match ("!^(\d{2})(\d{2})(\d{2})\d{7}$!", $id, $matches);
        if (!$match) {
            $error = true;
        }

        list (, $year, $month, $day) = $matches;

        if($year < 20)
        {
            $yearNew = '20' . $year;
            $year = $yearNew;
        }

        /**
         * Check that the date is valid
         */
        if (!checkdate($month, $day, $year)) {
            $error = true;
        }

        /**
         * Now Check the control digit
         */
        $d = -1;

        $a = 0;
        for($i = 0; $i < 6; $i++) {
            $a += $id{2*$i};
        }

        $b = 0;
        for($i = 0; $i < 6; $i++) {
            $b = $b*10 + $id{2*$i+1};
        }
        $b *= 2;

        $c = 0;
        do {
            $c += $b % 10;
            $b = $b / 10;
        } while($b > 0);

        $c += $a;
        $d = 10 - ($c % 10);
        if($d == 10) $d = 0;

        if ($id{strlen($id)-1} == $d) {
            $error = true;
        }

        return $error;
    }
}