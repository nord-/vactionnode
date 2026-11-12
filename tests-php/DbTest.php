<?php
namespace Tests;

use App\Db;
use App\Uuid;

final class DbTest extends DbTestCase
{
    public function testUpdateReportsMatchedRowsNotChangedRows(): void
    {
        // Samma getenv-mönster som DbTestCase. Hårdkodad 127.0.0.1:3307 är
        // värdens adress; inuti php-containern heter databasen db:3306 och
        // anslutningen hade fallit på connection refused.
        $pdo = Db::connect([
            'host' => getenv('DB_HOST') ?: 'db',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'name' => 'vacation_test',
            'user' => 'root',
            'pass' => 'root',
        ]);
        // Egen anslutning, alltså utanför testets transaktion. Städningen
        // måste därför ske i finally, annars blir raden kvar och nästa körning
        // faller på UNIQUE(email).
        $pdo->exec("INSERT INTO users (id, name, email, password_hash)
                    VALUES ('fr1', 'Anna', 'fr1@example.com', 'x')");

        try {
            // Samma värde som redan står där: noll ändrade rader, en matchad.
            $stmt = $pdo->prepare('UPDATE users SET name = ? WHERE id = ?');
            $stmt->execute(['Anna', 'fr1']);

            $this->assertSame(1, $stmt->rowCount());
        } finally {
            $pdo->exec("DELETE FROM users WHERE id = 'fr1'");
        }
    }

    public function testUuidV4HasCorrectShape(): void
    {
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            Uuid::v4()
        );
    }
}
