<?php

namespace App\Actions;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Monolog\Logger;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;
use function DI\value;
#[\AllowDynamicProperties]
abstract class Action
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $this->request = $request;
        $this->response = $response;
        $this->args = $args;
        $this->body = $this->request->getParsedBody();

        return $this->action();
    }

    abstract protected function action(): Response;

    /**
     * @return mixed
     * @throws HttpBadRequestException
     */
    protected function resolveArg(string $name)
    {
        if (!isset($this->args[$name])) {
            throw new HttpBadRequestException($this->request, "Could not resolve argument `{$name}`.");
        }

        return $this->args[$name];
    }
    protected function resolveParsedBody()
    {
        return $this->body;
    }
    protected function respondWithData($data = null, int $statusCode = 200, $description = null): Response
    {
        $payload = new ActionPayload($statusCode, $data, $description);

        return $this->respond($payload);
    }
    protected function respond(ActionPayload $payload): Response
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT);
        $this->response->getBody()->write($json);

        return $this->response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($payload->getStatusCode());
    }

    public function validateSaId($value)
    {
        // Remove any non-numeric characters and check length.
        $id = preg_replace('/[^0-9]/', '', $value);
        if (strlen($id) !== 13) {
            return false;
        }

        // 1. Extract and validate the date of birth (first 6 digits).
        $year = substr($id, 0, 2);
        $month = substr($id, 2, 2);
        $day = substr($id, 4, 2);

        $currentYear = (int)date('Y');
        $fullYear = (int)('19' . $year);
        if ($fullYear > $currentYear) {
            $fullYear = (int)('20' . $year);
        }
        if (!checkdate($month, $day, $fullYear)) {
            return false;
        }

        // 2. Validate the citizenship digit (11th digit).
        $citizenship = substr($id, 10, 1);
        if ($citizenship !== '0' && $citizenship !== '1') {
            return false;
        }

        // 3. Validate the checksum using the Luhn algorithm.
        $total = 0;
        $number = strrev(substr($id, 0, 12));
        for ($i = 0; $i < 12; $i++) {
            $digit = $number[$i];
            if ($i % 2 === 0) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit = ($digit - 9);
                }
            }
            $total += $digit;
        }

        $checksum = 10 - ($total % 10);
        if ($checksum === 10) {
            $checksum = 0;
        }

        return $checksum == substr($id, 12, 1);
    }
    public function getIDValues($value)
    {
        if(!$this->validateSaId($value)){
            return array(
                'status' => 'error',
                'description' => 'Id number not valid'
            );
        }

        $id = $value;

        // 1. Extract and validate the date of birth (first 6 digits).
        $year = substr($id, 0, 2);
        $month = substr($id, 2, 2);
        $day = substr($id, 4, 2);

        $currentYear = (int)date('Y');
        $fullYear = (int)('19' . $year);
        if ($fullYear > $currentYear) {
            $fullYear = (int)('20' . $year);
        }

        $dob = new \DateTime($year . '-' . $month . '-' . $day);

        $citizen_value = substr($id, 10,1);
        if($citizen_value == '0'){
            $citizen = 'South African Citizen';
        } elseif ($citizen_value == '1'){
            $citizen = 'Permanent Resident';
        } else {
            $citizen = 'Refugee (' . $citizen_value . ')';
        }

        $gender_value = substr($id, 6,4);
        if($gender_value >= '0000' && $gender_value <= '4999') {
            $gender = 'Female';
        } else {
            $gender = 'Male';
        }

        return array(
            'dob' => $dob->format('Y-m-d'),
            'citizen' => $citizen,
            'gender' => $gender
        );

    }
}