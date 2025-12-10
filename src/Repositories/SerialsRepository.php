<?php

namespace App\Repositories;

use PDO;
use Tuupola\Base62;

class SerialsRepository
{
    protected PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }
    /**
     * @return array
     */
    public function findAll(){
        $sql = <<<eof
SELECT * 
FROM serials
eof;
        $query = $this->pdo->prepare($sql);
        $this->pdo->beginTransaction();
        try {
            $query->execute();
            $this->pdo->commit();
            return $query->fetchAll();
        } catch(\PDOException $e) {
            $this->pdo->rollBack();
            // at this point you would want to implement some sort of error handling
            // or potentially re-throw the exception to be handled at a higher layer
            throw new \Exception($e->getMessage());
        }
    }
    public function findByUniqid($id){
        $sql = <<<eof
SELECT *
FROM serials a join products p on a.product_code = p.product_code
WHERE uniqid = ?
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
    public function findBySerial($id){
        $sql = <<<eof
SELECT * 
FROM serials 
WHERE serialno = ?
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
    public function findByActivation($id){
        $sql = <<<eof
SELECT * 
FROM serials s
JOIN products p on s.product_code = p.product_code
WHERE activationid = ?
eof;
        $query = $this->pdo->prepare($sql);
        $this->pdo->beginTransaction();
        try {
            $query->execute(array($id));
            $this->pdo->commit();
            return $query->fetch();
        } catch(\PDOException $e) {
            $this->pdo->rollBack();
            // at this point you would want to implement some sort of error handling
            // or potentially re-throw the exception to be handled at a higher layer
            throw new \Exception($e->getMessage());
        }
    }
    /**
     * Fetches product activation details by serial number.
     *
     * @param string $serialno
     * @return array|false Returns the row as an associative array or false if not found.
     */
    public function fetchProductActivation(string $serialno)
    {
        $sql = <<<SQL
            SELECT p.product_price
            FROM serials s
            JOIN products p ON s.product_code = p.product_code
            WHERE s.serialno = ?
            LIMIT 1
SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$serialno]);

        // Return an associative array (column_name => value)
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function addSerials($data){

        $sql = <<<eof
INSERT INTO serials
(product_code)
VALUES
    (?)
eof;
        $query = $this->pdo->prepare($sql);
        $this->pdo->beginTransaction();
        $count = 0;
        try {
            foreach ($data as $row){
                $db_values = array_values($row);
                $query->execute($db_values);
                $count++;
            }
            $this->pdo->commit();
            return $count;
        } catch(\PDOException $e) {
            $this->pdo->rollBack();
            // at this point you would want to implement some sort of error handling
            // or potentially re-throw the exception to be handled at a higher layer
            throw new \Exception($e->getMessage());
        }
    }
    public function addSerial($data){

        //create serial for a single product
        $sql = <<<eof
INSERT INTO serials
(product_code, cellno, channel, sales_agent)
VALUES
    (?, ?, ?, ?)
eof;
        $query = $this->pdo->prepare($sql);
        $this->pdo->beginTransaction();
        try {
            $query->execute($data);
            $id = $this->pdo->lastInsertId();
            $this->pdo->commit();
            //update after commit
            $uniqid = $this->updateSerialno($id);
            return array(
                'id' => $id,
                'uniqid' => $uniqid,
                'serialno' => $id . $data[0]
            );
        } catch(\PDOException $e) {
            $this->pdo->rollBack();
            // at this point you would want to implement some sort of error handling
            // or potentially re-throw the exception to be handled at a higher layer
            throw new \Exception($e->getMessage());
        }


    }

    private function updateSerialno($id){
        //update serialno
        //update uniqid from AuotInc
        $base62 = new Base62;
        $uniqid = $base62->encode($id);

        $sql = <<<eof
UPDATE serials
SET serialno = concat(id, product_code), uniqid = '$uniqid'
WHERE id = ?;
eof;
        $query = $this->pdo->prepare($sql);
        $this->pdo->beginTransaction();
        try {
            $query->execute(array($id));
            $this->pdo->commit();
            return $uniqid;
        } catch(\PDOException $e) {
            $this->pdo->rollBack();
            // at this point you would want to implement some sort of error handling
            // or potentially re-throw the exception to be handled at a higher layer
            throw new \Exception($e->getMessage());
        }

    }

    public function updateActivation($serialno, $activationid){
        $sql = <<<eof
UPDATE serials SET activationid = ?, current_status = 'ACTIVATED' WHERE serialno = ?
eof;
        $query = $this->pdo->prepare($sql);
        $this->pdo->beginTransaction();
        try {
            $query->execute(array($activationid,$serialno));
            $this->pdo->commit();
            return array(
                    'serialno' => $serialno,
                    'activationid' => $activationid
            );
        } catch(\PDOException $e) {
            $this->pdo->rollBack();
            // at this point you would want to implement some sort of error handling
            // or potentially re-throw the exception to be handled at a higher layer
            throw new \Exception($e->getMessage());
        }
    }
    public function updateReplacement($activationid){
        $sql = <<<eof
UPDATE serials SET replacement = 1 WHERE activationid = ?
eof;
        $query = $this->pdo->prepare($sql);
        $this->pdo->beginTransaction();
        try {
            $query->execute(array($activationid));
            $this->pdo->commit();
            return true;
        } catch(\PDOException $e) {
            $this->pdo->rollBack();
            // at this point you would want to implement some sort of error handling
            // or potentially re-throw the exception to be handled at a higher layer
            throw new \Exception($e->getMessage());
        }
    }

    public function changeStatus($activationid, $status){
        $sql = <<<eof
UPDATE serials SET current_status = ? WHERE activationid = ?
eof;
        $query = $this->pdo->prepare($sql);
        $this->pdo->beginTransaction();
        try {
            $query->execute(array($status,$activationid));
            $this->pdo->commit();
            return array(
                    'activationid' => $activationid,
                    'status' => $status
            );
        }   catch (\PDOException $e) {
            $this->pdo->rollBack();
            // at this point you would want to implement some sort of error handling
            // or potentially re-throw the exception to be handled at a higher layer
            throw new \Exception($e->getMessage());
        }

    }
    public function changeStatusJson($activationid, $status, $json){
        $sql = <<<eof
UPDATE serials SET current_status = ?, status_result = ? WHERE activationid = ?
eof;
        $query = $this->pdo->prepare($sql);
        $this->pdo->beginTransaction();
        try {
            $query->execute(array($status,$json,$activationid));
            $this->pdo->commit();
            return array(
                    'activationid' => $activationid,
                    'status' => $status
            );
        }   catch (\PDOException $e) {
            $this->pdo->rollBack();
            // at this point you would want to implement some sort of error handling
            // or potentially re-throw the exception to be handled at a higher layer
            throw new \Exception($e->getMessage());
        }

    }
}