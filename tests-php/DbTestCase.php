<?php
namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;

abstract class DbTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        // Testerna körs inuti php-containern, där databasen heter db:3306.
        // Miljövariablerna gör det möjligt att peka om utan kodändring.
        $host = getenv('DB_HOST') ?: 'db';
        $port = getenv('DB_PORT') ?: '3306';

        $this->pdo = new PDO(
            "mysql:host={$host};port={$port};dbname=vacation_test;charset=utf8mb4",
            'root',
            'root',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_FOUND_ROWS   => true,
            ]
        );
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }
}
