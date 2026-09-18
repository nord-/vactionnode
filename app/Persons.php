<?php
namespace App;

use PDO;

final class Persons
{
    public static function create(
        PDO $pdo,
        string $name,
        string $email,
        string $password,
        ?string $groupId = null,
        bool $isAdmin = false
    ): array {
        $id = Uuid::v4();

        $stmt = $pdo->prepare(
            'INSERT INTO users (id, name, email, password_hash, team_id, is_admin)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $id,
            $name,
            strtolower(trim($email)),
            password_hash($password, PASSWORD_DEFAULT),
            $groupId,
            (int) $isAdmin,
        ]);

        return [
            'id'      => $id,
            'name'    => $name,
            'email'   => strtolower(trim($email)),
            'groupId' => $groupId,
            'isAdmin' => $isAdmin,
        ];
    }

    /**
     * Låser adminraderna innan de räknas. Utan FOR UPDATE ser två samtidiga
     * degraderingar i REPEATABLE READ båda samma snapshot, båda lyckas, och
     * systemet blir kvar utan administratör.
     *
     * Måste anropas inuti en transaktion, annars släpps låset direkt.
     */
    public static function wouldLeaveNoAdmin(PDO $pdo, string $userId): bool
    {
        $admins = $pdo->query('SELECT id FROM users WHERE is_admin = 1 FOR UPDATE')
                      ->fetchAll(PDO::FETCH_COLUMN);

        return count($admins) === 1 && $admins[0] === $userId;
    }
}
