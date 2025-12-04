<?php

namespace App\Repositories;

use PDO;

class UserRepository
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
FROM users
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
    /**
     * @param int $id
     * @return array
     * @throws
     */
    public function findUserOfId(int $id){
        $sql = <<<eof
SELECT * 
FROM users
WHERE id = ?
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
}