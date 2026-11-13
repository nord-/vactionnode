<?php
namespace App;

use PDO;

final class Auth
{
    public static function start(array $config): void
    {
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Secure läses ur config, aldrig ur $_SERVER['HTTPS'] — den är tom
        // bakom DSM:s reverse proxy och hade stängt av flaggan i produktion.
        // Default är true; bara dev-config.php sätter false.
        session_set_cookie_params([
            'httponly' => true,
            'secure'   => $config['cookie_secure'] ?? true,
            'samesite' => 'Strict',
            'path'     => '/',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();
    }

    public static function currentUser(PDO $pdo): ?array
    {
        $id = $_SESSION['user_id'] ?? null;
        if ($id === null) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT id, name, email, team_id AS groupId, is_admin AS isAdmin,
                    password_hash IS NOT NULL AS hasPassword
             FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $row['isAdmin'] = (bool) $row['isAdmin'];
        // Microsoft-only-konton saknar lösenord; frontend döljer då
        // "Byt lösenord", som annars bara hade kunnat misslyckas.
        $row['hasPassword'] = (bool) $row['hasPassword'];

        return $row;
    }

    /**
     * Slår upp raden innan handlern körs. Det är uppslaget som gör att 404
     * och 403 går att skilja åt — en UPDATE som påverkar noll rader kan inte
     * säga om raden saknas eller tillhör någon annan.
     */
    public static function requireOwner(PDO $pdo, string $vacationId, array $user): void
    {
        $stmt = $pdo->prepare('SELECT user_id FROM vacations WHERE id = ?');
        $stmt->execute([$vacationId]);
        $ownerId = $stmt->fetchColumn();

        if ($ownerId === false) {
            throw new HttpException('not_found', 'Semesterraden finns inte.', 404);
        }
        if ($ownerId !== $user['id'] && !$user['isAdmin']) {
            throw new HttpException('forbidden', 'Du får bara ändra din egen semester.', 403);
        }
    }
}
