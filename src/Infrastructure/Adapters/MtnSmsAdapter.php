<?php
namespace App\Infrastructure\Adapters;

class MtnSmsAdapter
{
    private string $baseUrl = 'https://sms01.umsg.co.za/send/sms';

    public function __construct(
        private string $apiUsername,
        private string $apiPassword
    ) {}

    public function send(string $to, string $message, string $userRef): array
    {
        $formattedNumber = $this->formatMobileNumber($to);

        $payload = [
            "to"      => $formattedNumber,
            "message" => $message,
            "ems"     => "0",
            "userref" => $userRef
        ];

        $ch = curl_init($this->baseUrl);

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,

            // --- ENABLE HEADER CAPTURE ---
            // This tells cURL to include the headers in the output string
            CURLOPT_HEADER         => true,

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

        // Execute request
        $rawOutput = curl_exec($ch);

        // Get Info / Errors
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curlErrNo = curl_errno($ch);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($curlErrNo !== 0) {
            return [
                'success' => false,
                'error'   => "cURL Error: $curlError",
                'raw_debug' => null
            ];
        }

        // --- SPLIT HEADERS AND BODY ---
        // The $rawOutput contains both. We use $headerSize to cut them apart.
        $responseHeaders = substr($rawOutput, 0, $headerSize);
        $responseBody    = substr($rawOutput, $headerSize);

        // Parse Body
        $responseData = json_decode($responseBody, true);

        // --- LOGIC CHECKS ---
        $resultCode = $responseData['Result'] ?? 0;
        $isApiSuccess = ($resultCode == 1);
        $finalSuccess = ($httpCode >= 200 && $httpCode < 300) && $isApiSuccess;

        $errorMessage = null;
        if (!$finalSuccess) {
            $errorMessage = $httpCode >= 400
                ? "HTTP Error $httpCode"
                : "Provider Error: " . ($responseData['Error'] ?? 'Unknown');
        }

        return [
            'success'     => $finalSuccess,
            'status_code' => $httpCode,
            'body'        => $responseBody, // The clean JSON string
            'parsed'      => $responseData, // The PHP Array
            'headers'     => $responseHeaders, // The Raw Headers string
            'error'       => $errorMessage
        ];
    }

    private function formatMobileNumber(string $number): string
    {
        $clean = preg_replace('/\D/', '', $number);
        if (str_starts_with($clean, '0') && strlen($clean) === 10) return '27' . substr($clean, 1);
        if (strlen($clean) === 9) return '27' . $clean;
        return $clean;
    }
}