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
        // 1. INSERT: Log to DB as 'pending' before attempting to send
        // This ensures you have a record even if the script crashes
        $stmt = $this->db->prepare("
            INSERT INTO sms_logs (recipient, message, user_ref, status, insertdt) 
            VALUES (:to, :msg, :ref, 'pending', NOW())
        ");

        $stmt->execute([
            'to'  => $to,
            'msg' => $message,
            'ref' => $userRef
        ]);

        $logId = $this->db->lastInsertId();

        // 2. ACTION: Send via cURL Adapter
        $result = $this->adapter->send($to, $message, $userRef);

        // 3. UPDATE: Log the result
        $status = $result['success'] ? 'sent' : 'failed';

        // If failed, store the error; if success, store the API response body
        $apiResponse = $result['success'] ? $result['body'] : ($result['error'] ?? $result['body']);

        $updateStmt = $this->db->prepare("
            UPDATE sms_logs 
            SET status = :status, api_response = :response 
            WHERE id = :id
        ");

        $updateStmt->execute([
            'status'   => $status,
            'response' => $apiResponse,
            'id'       => $logId
        ]);

        return [
            'log_id' => $logId,
            'status' => $status,
            'data'   => $result
        ];
    }
}