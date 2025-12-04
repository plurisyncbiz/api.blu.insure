<?php

namespace App\Actions;

use App\Actions\Action;
use Psr\Http\Message\ResponseInterface as Response;
use App\Domain\Services\SmsService;

class SendSmsAction extends Action
{
    public function __construct(private SmsService $smsService) {}
    protected function action(): Response
    {
        $data = $this->resolveParsedBody();

        $to      = $data['to'] ?? null;
        $message = $data['message'] ?? null;
        $userRef = $data['userref'] ?? uniqid('ref_');

        // Simple Validation
        if (empty($to) || empty($message)) {
            $payload = 'Fields to and message are required.';
            // Return Response
            return $this->respondWithData('', 400, $payload);
        }

        // Call Service
        $result = $this->smsService->processSms($to, $message, $userRef);

        // Return Response
        return $this->respondWithData($result, 200, 'Message Submitted');
    }
}