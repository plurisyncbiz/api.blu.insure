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
            CURLOPT_HEADER         => true, // Capture headers
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

        $rawOutput = curl_exec($ch);

        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curlErrNo  = curl_errno($ch);
        $curlError  = curl_error($ch);

        curl_close($ch);

        if ($curlErrNo !== 0) {
            return [
                'success'   => false,
                'error'     => "cURL Error: $curlError",
                'raw_debug' => null
            ];
        }

        // Split Headers and Body
        $responseHeaders = substr($rawOutput, 0, $headerSize);
        $responseBody    = substr($rawOutput, $headerSize);
        $responseData    = json_decode($responseBody, true);

        // --- FIXED LOGIC START ---

        // 1. Normalize Keys: Check for 'result' OR 'Result'
        // This handles case-sensitivity issues safely
        $apiResult = $responseData['result'] ?? $responseData['Result'] ?? 0;
        $apiError  = $responseData['error']  ?? $responseData['Error']  ?? null;

        // 2. Check Success (Loose comparison matches "1" string or 1 integer)
        $isApiSuccess = ($apiResult == 1);

        // 3. Final Success (HTTP 200 + API Success)
        $finalSuccess = ($httpCode >= 200 && $httpCode < 300) && $isApiSuccess;

        $errorMessage = null;
        if (!$finalSuccess) {
            if ($httpCode >= 400) {
                $errorMessage = "HTTP Error $httpCode";
            } else {
                // Determine the specific error message
                $errorMessage = "Provider Error: " . ($apiError ?? 'Unknown failure');
            }
        }
        // --- FIXED LOGIC END ---

        return [
            'success'     => $finalSuccess,
            'status_code' => $httpCode,
            'body'        => $responseBody,
            'parsed'      => $responseData,
            'headers'     => $responseHeaders,
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