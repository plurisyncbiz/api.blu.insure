<?php
namespace App\Repositories;

use PDO;
use PDOException;
class ProductsRepository
{
    protected PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    public function fetchAll()
    {
        $statement = $this->pdo->prepare("SELECT * FROM products");
        $statement->execute();
        return $statement->fetchAll();
    }

    public function fetchById($id){
        $statement = $this->pdo->prepare("SELECT * FROM products WHERE product_code = :id");
        $statement->bindParam(":id", $id);
        $statement->execute();
        return $statement->fetch();
    }
}