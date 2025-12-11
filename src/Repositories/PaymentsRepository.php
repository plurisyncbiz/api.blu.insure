<?php

namespace App\Repositories;

use PDO;
use PDOException;

class PaymentsRepository
{
    protected PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    public function fetch($id){
        $sql = "SELECT * FROM payments WHERE activationid = :id";
        $query = $this->pdo->prepare($sql);
        $query->execute(['id' => $id]);
        return $query->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data){
        $sql = <<<eof
            INSERT INTO payments
            (uuid, acc_no, bank, branch_code, current_status, debit_date, activationid, entity_object) 
            VALUES     
            (uuid(), ?, ?, ?, 'NEW', ?, ?, ?)
eof;
        $query = $this->pdo->prepare($sql);
        $this->pdo->beginTransaction();
        try {
            $query->execute($data);
            $id = $this->pdo->lastInsertId();
            $this->pdo->commit();
            return array(
                'status' => 'success',
                'id' => $id
            );
        } catch(PDOException $e) {
            $this->pdo->rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    // --- NEW UPDATE METHOD ---
    public function update($data){
        $sql = <<<eof
            UPDATE payments 
            SET acc_no = ?, 
                bank = ?, 
                branch_code = ?, 
                debit_date = ?, 
                entity_object = ?
            WHERE activationid = ?
eof;
        $query = $this->pdo->prepare($sql);
        $this->pdo->beginTransaction();
        try {
            $query->execute($data);
            $this->pdo->commit();
            return array(
                'status' => 'updated'
            );
        } catch(PDOException $e) {
            $this->pdo->rollBack();
            throw new \Exception($e->getMessage());
        }
    }

    public function updateMandateJson(int $activationId, string $jsonData): bool
    {
        $sql = "UPDATE payments SET mandate_object = :json WHERE activationid = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            'json' => $jsonData,
            'id' => $activationId
        ]);
    }
}