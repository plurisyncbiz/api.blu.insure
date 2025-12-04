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

    public function create($data){

        //return $data;
        $sql = <<<eof
INSERT INTO payments
(uuid, acc_no, bank, branch_code, current_status, debit_date, activationid, entity_object) 
 VALUES     
    (uuid(), ?,?,?,'NEW',?,?, ?)
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
            // at this point you would want to implement some sort of error handling
            // or potentially re-throw the exception to be handled at a higher layer
            throw new \Exception($e->getMessage());
        }
    }

    public function fetch($id){
        $sql = <<<eof
        SELECT * FROM payments WHERE activationid = :id
eof;
        $query = $this->pdo->prepare($sql);
        $query->execute(['id' => $id]);
        return $query->fetch(PDO::FETCH_ASSOC);
    }
}