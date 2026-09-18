<?php
namespace App\Handlers;

use App\Http;
use App\RateLimit;
use PDO;

final class SessionHandler
{
    // Äkta bcrypt-hash av en slängsträng, aldrig ett giltigt lösenord för
    // något konto. Finns bara för att password_verify() ska köras exakt en
    // gång oavsett gren — annars läcker svarstiden vilken av de tre
    // avvisningsorsakerna (okänt konto, NULL-hash, fel lösenord) det var,
    // trots att svarskroppen är identisk. Genererad med samma PASSWORD_DEFAULT
    // som de riktiga hasharna, så kostnadsfaktorn matchar.
    private const DUMMY_HASH = '$2y$10$Mch47B42oJxevZqkNmE5yeBUHn5XDN8WVOIjuZSn.v9QKo7BMYlLK';

    public function __construct(private PDO $pdo) {}

    public function login(?array $user, ?string $id = null): void
    {
        $body     = Http::body();
        $email    = strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');
        $config   = require dirname(__DIR__, 2) . '/config.php';
        $ip       = RateLimit::clientIp($config, $_SERVER);

        if (RateLimit::tooManyAttempts($this->pdo, $email, $ip)) {
            Http::error('too_many_attempts', 'För många försök. Vänta en stund.', 429);
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, team_id AS groupId, is_admin AS isAdmin, password_hash
             FROM users WHERE email = ?'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        $hashExists = $row !== false && $row['password_hash'] !== null;
        // Verifiera alltid mot en hash — dummyn när kontot saknas eller
        // saknar lösenord — så att bcrypt-kostnaden är densamma oavsett
        // gren och svarstiden inte avslöjar vilken avvisningsorsak det var.
        $passwordOk = password_verify($password, $hashExists ? $row['password_hash'] : self::DUMMY_HASH);

        // Samma svar oavsett om kontot saknas, saknar lösenord eller har fel
        // lösenord — annars blir endpointen en kontolista.
        if (!$hashExists || !$passwordOk) {
            RateLimit::record($this->pdo, $email, $ip);
            Http::error('invalid_credentials', 'Fel e-post eller lösenord.', 401);
            return;
        }

        // Guardad som logout: i CLI (PHPUnit) finns ingen aktiv session, och
        // session_regenerate_id() ger då en PHP-varning som gör testet rött.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $row['id'];

        unset($row['password_hash']);
        $row['isAdmin'] = (bool) $row['isAdmin'];
        Http::json($row);
    }

    public function me(?array $user, ?string $id = null): void
    {
        Http::json($user);
    }

    public function logout(?array $user, ?string $id = null): void
    {
        $_SESSION = [];
        if (PHP_SAPI !== 'cli') {
            session_destroy();
        }
        Http::json(['ok' => true]);
    }

    public function changePassword(?array $user, ?string $id = null): void
    {
        $body    = Http::body();
        $current = (string) ($body['currentPassword'] ?? '');
        $new     = (string) ($body['newPassword'] ?? '');

        if (strlen($new) < 8) {
            Http::error('validation_failed', 'Nytt lösenord måste vara minst 8 tecken.', 422);
            return;
        }

        $stmt = $this->pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hash = $stmt->fetchColumn();

        if ($hash === null || !password_verify($current, $hash)) {
            Http::error('validation_failed', 'Nuvarande lösenord stämmer inte.', 422);
            return;
        }

        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);

        Http::json(['ok' => true]);
    }
}
