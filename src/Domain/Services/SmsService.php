<?php
namespace App\Domain\Services;

use App\Infrastructure\Adapters\MtnSmsAdapter;
use PDO;

class SmsService
{
    public function __construct(
        private PDO $db,
        private MtnSmsAdapter $adapter
    ) {}

    public function processSms(string $to, string $message, string $userRef): array
    {
        // 1. Log Pending
        $stmt = $this->db->prepare("
            INSERT INTO sms_logs (recipient, message, user_ref, status, created_at) 
            VALUES (:to, :msg, :ref, 'pending', NOW())
        ");
        $stmt->execute(['to' => $to, 'msg' => $message, 'ref' => $userRef]);
        $logId = $this->db->lastInsertId();

        // 2. Send
        $result = $this->adapter->send($to, $message, $userRef);

        // 3. Prepare Full Debug Log
        // We pack Headers + Body into one JSON object for storage
        $debugLog = json_encode([
            'http_code' => $result['status_code'] ?? 0,
            'response_body' => $result['parsed'] ?? $result['body'], // Store array if possible, else string
            'response_headers' => explode("\r\n", $result['headers'] ?? ''), // Split headers into array for readability
            'error_msg' => $result['error'] ?? null
        ]);

        // 4. Update DB
        $status = $result['success'] ? 'sent' : 'failed';

        $updateStmt = $this->db->prepare("
            UPDATE sms_logs 
            SET status = :status, api_response = :response 
            WHERE id = :id
        ");

        $updateStmt->execute([
            'status'   => $status,
            'response' => $debugLog, // Saves the full debug info
            'id'       => $logId
        ]);

        return ['log_id' => $logId, 'status' => $status, 'data' => $result];
    }
}