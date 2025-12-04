<?php

namespace App\Repositories;

use PDO;
use Doctrine\DBAL\Connection;
use PDOException;
class PolicyHolderRepository
{
    protected PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    public function create($data){

        //return $data;
        $sql = <<<eof
INSERT INTO lives_insured
(uuid, name, surname, cellno, email, idno, relationship, activationid, gender, dob, entity_object) 
VALUES
    (uuid(), ?,?,?,?,?,?,?,?,?,?)
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
        } catch(\PDOException $e) {
            $this->pdo->rollBack();
            // at this point you would want to implement some sort of error handling
            // or potentially re-throw the exception to be handled at a higher layer
            throw new \Exception($e->getMessage());
        }

    }

    public function getMainLifeById($id)
    {
        $statement = $this->pdo->prepare("SELECT * FROM lives_insured WHERE activationid = :id AND relationship = 'MAIN'");
        $statement->bindParam(":id", $id);
        $statement->execute();
        return $statement->fetch();
    }

    public function getBeneficiariesById($id)
    {
        $statement = $this->pdo->prepare("SELECT * FROM lives_insured WHERE activationid = :id AND relationship = 'BENEFICIARY'");
        $statement->bindParam(":id", $id);
        $statement->execute();
        return $statement->fetchAll();
    }

}