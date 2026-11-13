<?php
namespace App\Sso;

use App\Uuid;
use PDO;

/**
 * Kopplar en Entra ID-identitet (oid) till ett konto i users.
 */
final class SsoAccounts
{
    /**
     * Ger id:t för kontot som identiteten loggar in som. Raden läses sedan
     * via Auth, så användarens form finns på ett enda ställe.
     */
    public static function resolve(PDO $pdo, string $oid, string $email, string $name): string
    {
        $id = self::findId($pdo, 'sso_id', $oid);
        if ($id !== null) {
            return $id;
        }

        $email = strtolower(trim($email));
        $id = self::findId($pdo, 'email', $email);
        if ($id !== null) {
            self::link($pdo, $id, $oid);

            return $id;
        }

        return self::provision($pdo, $oid, $email, $name);
    }

    /**
     * Alla i tenanten släpps in (beslut 2026-11-12). Kontot får aldrig
     * admin och ingen grupp — personen hamnar i "Ogrupperade" tills en
     * administratör flyttar hen.
     */
    private static function provision(PDO $pdo, string $oid, string $email, string $name): string
    {
        $name = mb_substr(trim($name), 0, 100) ?: $email;
        $id   = Uuid::v4();

        $stmt = $pdo->prepare(
            'INSERT INTO users (id, name, email, sso_id, password_hash, is_admin, team_id)
             VALUES (?, ?, ?, ?, NULL, 0, NULL)'
        );
        try {
            $stmt->execute([$id, $name, $email, $oid]);
        } catch (\PDOException $e) {
            // Två första inloggningar i samma ögonblick: den andra stöter
            // på UNIQUE. Då har den första redan skapat kontot, så vi
            // återanvänder det i stället för att svara med ett fel.
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            $existing = self::findId($pdo, 'sso_id', $oid);
            if ($existing === null) {
                $existing = self::findId($pdo, 'email', $email);
                if ($existing === null) {
                    throw $e;
                }
                self::link($pdo, $existing, $oid);
            }

            return $existing;
        }

        return $id;
    }

    /**
     * Länkningen sker exakt en gång per person. Lösenordet rensas samtidigt
     * (beslut 2026-11-12): kontot blir Microsoft-only och den svagare
     * inloggningen försvinner ur en publikt exponerad app.
     *
     * Villkoret sso_id IS NULL (eller redan samma oid, för en samtidig
     * inloggning av samma person) står i UPDATE:n, inte i en separat kontroll,
     * så att två samtidiga första inloggningar inte båda kan vinna. Ett konto
     * som redan har ett annat oid skrivs aldrig över: e-posten kan ha gått
     * vidare till en ny person i tenanten.
     */
    private static function link(PDO $pdo, string $id, string $oid): void
    {
        $stmt = $pdo->prepare(
            'UPDATE users SET sso_id = ?, password_hash = NULL
             WHERE id = ? AND (sso_id IS NULL OR sso_id = ?)'
        );
        $stmt->execute([$oid, $id, $oid]);

        if ($stmt->rowCount() === 0) {
            throw new SsoException(
                'account_conflict',
                "Kontot $id är redan kopplat till en annan Microsoft-identitet."
            );
        }
    }

    private static function findId(PDO $pdo, string $column, string $value): ?string
    {
        // $column kommer bara från den här klassen, aldrig från indata.
        $stmt = $pdo->prepare("SELECT id FROM users WHERE $column = ?");
        $stmt->execute([$value]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : $id;
    }
}
