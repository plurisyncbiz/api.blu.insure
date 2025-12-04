<?php

namespace App\Infrastructure\Adapters;
use RuntimeException;
#[\AllowDynamicProperties]
class MtnSmsAdapter
{
    private string $baseUrl = 'https://sms01.umsg.co.za/send/sms';

    public function __construct(
        private string $apiUsername,
        private string $apiPassword
    ) {}

    public function send(string $to, string $message, string $userRef): array
    {
        // 1. Format Number (Clean to 27XXXXXXXXX)
        $formattedNumber = $this->formatMobileNumber($to);

        $payload = [
            "to"      => $formattedNumber,
            "message" => $message,
            "ems"     => "1",
            "userref" => $userRef
        ];

        $ch = curl_init($this->baseUrl);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => "{$this->apiUsername}:{$this->apiPassword}",
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Connection: close'
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrNo    = curl_errno($ch);
        $curlError    = curl_error($ch);

        curl_close($ch);

        // --- TRANSPORT ERRORS (No connection, DNS fail, etc) ---
        if ($curlErrNo !== 0) {
            return [
                'success' => false,
                'error'   => "cURL Transport Error: $curlError"
            ];
        }

        // --- API RESPONSE HANDLING ---
        $responseData = json_decode($responseBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error'   => "Invalid JSON received",
                'body'    => $responseBody
            ];
        }
        // 1. Get Result (Default to 0/Failure if missing)
        $resultCode = $responseData['result'] ?? 0;

        // 2. Check Success: Explicitly check for 1 (int or string '1')
        $isApiSuccess = ($resultCode == 1);

        // 3. Determine Final Status
        // It must be HTTP 200 OK AND Result 1
        $finalSuccess = ($httpCode >= 200 && $httpCode < 300) && $isApiSuccess;

        // 4. Extract Error Message if failed
        $errorMessage = null;
        if (!$finalSuccess) {
            if ($httpCode >= 400) {
                $errorMessage = "HTTP Error $httpCode";
            } else {
                // If HTTP was fine but API returned Result: 0
                // We capture the "Error" field or "message" depending on what the API sends back
                $errorMessage = "Provider Error: " . ($responseData['error'] ?? 'Unknown failure');
            }
        }

        return [
            'success'     => $finalSuccess,
            'status_code' => $httpCode,
            'body'        => $responseBody,
            'parsed'      => $responseData,
            'error'       => $errorMessage
        ];
    }

    /**
     * Converts various SA formats to 27XXXXXXXXX
     * * Input Examples:
     * - 082 123 4567  -> 27821234567
     * - +27821234567  -> 27821234567
     * - 27 82 123...  -> 27821234567
     */
    private function formatMobileNumber(string $number): string
    {
        // 1. Remove all non-numeric characters (spaces, +, -, brackets)
        $clean = preg_replace('/\D/', '', $number);

        // 2. Check if it starts with '0' (Standard Local: 082...)
        // South African numbers are 10 digits when they start with 0
        if (str_starts_with($clean, '0') && strlen($clean) === 10) {
            return '27' . substr($clean, 1);
        }

        // 3. Check if it already starts with '27' (e.g. 2782...)
        // It should be 11 digits long
        if (str_starts_with($clean, '27') && strlen($clean) === 11) {
            return $clean;
        }

        // 4. Fallback: If it's just 9 digits (e.g. 821234567), prepend 27
        if (strlen($clean) === 9) {
            return '27' . $clean;
        }

        // Return original cleaned version if we can't determine format
        return $clean;
    }
}