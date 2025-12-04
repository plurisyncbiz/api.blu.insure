<?php

namespace App\Repositories;

use PDO;
use PDOException;
use Symfony\Component\Uid\Uuid;

class ActivationsRepository
{
    protected PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    public function create($data){

        $sql = <<<eof
INSERT INTO activations
(uuid, serialno, ip_address, user_agent) 
VALUES
    (UUID(), ?,?,?)
eof;
        $query = $this->pdo->prepare($sql);
        $this->pdo->beginTransaction();
        try {
            $query->execute($data);
            $id = $this->pdo->lastInsertId();
            $this->pdo->commit();
            return $id;
        } catch(\PDOException $e) {
            $this->pdo->rollBack();
            // at this point you would want to implement some sort of error handling
            // or potentially re-throw the exception to be handled at a higher layer
            throw new \Exception($e->getMessage());
        }

    }
    public function fetch($id){
        $sql = <<<eof
SELECT * 
FROM activations a
JOIN serials s on a.serialno = s.serialno
JOIN products p on s.product_code = p.product_code
WHERE a.uniqid = ?
eof;
        $query = $this->pdo->prepare($sql);
        $this->pdo->beginTransaction();
        try {
            $query->execute(array($id));
            $this->pdo->commit();
            return $query->fetchAll();
        } catch(\PDOException $e) {
            $this->pdo->rollBack();
            // at this point you would want to implement some sort of error handling
            // or potentially re-throw the exception to be handled at a higher layer
            throw new \Exception($e->getMessage());
        }

    }
    public function exists($id){
        $sql = <<<eof
SELECT count(*)
FROM activations a
JOIN serials s on a.serialno = s.serialno
JOIN products p on s.product_code = p.product_code
WHERE a.activationid = ?
and !isnull(a.activationid)
eof;
        $query = $this->pdo->prepare($sql);
        $this->pdo->beginTransaction();
        try {
            $query->execute(array($id));
            $this->pdo->commit();
            return $query->fetchColumn();
        } catch(\PDOException $e) {
            $this->pdo->rollBack();
            // at this point you would want to implement some sort of error handling
            // or potentially re-throw the exception to be handled at a higher layer
            throw new \Exception($e->getMessage());
        }

    }

    private function createSMSQueue($qid){
        $sql = <<<eof
UPDATE activations SET current_status = 'QUEUE', status_result = 'QUEUE Created', qid = '$qid' WHERE channel = 'USSD' and current_status  = 'NEW'
eof;
        $query = $this->pdo->prepare($sql);
        $this->pdo->beginTransaction();
        try {
            $query->execute();
            $this->pdo->commit();
            return $qid;
        } catch(\PDOException $e) {
            $this->pdo->rollBack();
            // at this point you would want to implement some sort of error handling
            // or potentially re-throw the exception to be handled at a higher layer
            return $e->errorInfo;
        }

    }

    public function fetchSMS(){
        $qid = uniqid("", true);
        $result = $this->createSMSQueue($qid);
        if($result == $qid){
            $sql = <<<eof
SELECT * FROM activations WHERE qid = ?
eof;
            $query = $this->pdo->prepare($sql);
            $this->pdo->beginTransaction();
            try {
                $query->execute(array($qid));
                $this->pdo->commit();
                return $query->fetchAll();
            } catch(\PDOException $e) {
                $this->pdo->rollBack();
                // at this point you would want to implement some sort of error handling
                // or potentially re-throw the exception to be handled at a higher layer
                return $e->errorInfo;
            }
        } else {
            return $result;
        }

    }
    public function fetchBySerial($id){
        $sql = <<<eof
SELECT * FROM activations WHERE serialno = ?
eof;
        $query = $this->pdo->prepare($sql);
        $this->pdo->beginTransaction();
        try {
            $query->execute(array($id));
            $this->pdo->commit();
            return $query->fetchAll();
        } catch(\PDOException $e) {
            $this->pdo->rollBack();
            // at this point you would want to implement some sort of error handling
            // or potentially re-throw the exception to be handled at a higher layer
            return $e->errorInfo;
        }

    }

    public function updateUniqud($uniqid, $id){
        $sql = <<<eof
UPDATE activations SET uniqid = ? WHERE activationid = ?
eof;
        $query = $this->pdo->prepare($sql);
        $this->pdo->beginTransaction();
        try {
            $query->execute(array($uniqid, $id));
            $this->pdo->commit();
            return $uniqid;
        } catch(PDOException $e) {
            $this->pdo->rollBack();
            // at this point you would want to implement some sort of error handling
            // or potentially re-throw the exception to be handled at a higher layer
            return $e->errorInfo;
        }
    }

    public function updateActivationIdno($idno, $id){
        $sql = <<<eof
UPDATE activations SET idno = ? WHERE activationid = ?
eof;
        $query = $this->pdo->prepare($sql);
        $this->pdo->beginTransaction();
        try {
            $query->execute(array($idno, $id));
            $this->pdo->commit();
            return array(
                'id' => $id
            );
        } catch(PDOException $e) {
            $this->pdo->rollBack();
            // at this point you would want to implement some sort of error handling
            // or potentially re-throw the exception to be handled at a higher layer
            throw new \Exception($e->getMessage());
        }
    }
}