<?php
namespace App;

use PDO;

final class RateLimit
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;

    /**
     * X-Forwarded-For är klientstyrd och trivial att förfalska. Headern läses
     * därför bara när avsändaren själv står i trusted_proxies, och då används
     * sista hoppet — det är det enda som proxyn själv har satt.
     * Tom lista stänger av per-IP-spärren, vilket är standard: bakom DSM:s
     * reverse proxy är REMOTE_ADDR proxyns adress, och en spärr på den skulle
     * låsa ute alla samtidigt.
     */
    public static function clientIp(array $config, array $server): ?string
    {
        $trusted = $config['trusted_proxies'] ?? [];
        if ($trusted === []) {
            return null;
        }

        $remote = $server['REMOTE_ADDR'] ?? null;
        if ($remote === null || !in_array($remote, $trusted, true)) {
            return $remote;
        }

        $hops = array_map('trim', explode(',', $server['HTTP_X_FORWARDED_FOR'] ?? ''));
        $last = end($hops);

        // Ett trasigt sista hopp får aldrig nå inet_pton(): det binds då som
        // '' och kolliderar med NULL-IP-sentinelen, så en enda felformad
        // header kan låsa ute alla null-IP-försök samtidigt. Validera innan
        // retur; falla tillbaka på REMOTE_ADDR om den själv är giltig,
        // annars null.
        if ($last !== '' && filter_var($last, FILTER_VALIDATE_IP) !== false) {
            return $last;
        }

        return filter_var($remote, FILTER_VALIDATE_IP) !== false ? $remote : null;
    }

    public static function tooManyAttempts(PDO $pdo, string $email, ?string $ip): bool
    {
        self::purge($pdo);

        $sql = 'SELECT COUNT(*) FROM login_attempts
                WHERE attempted_at > NOW() - INTERVAL ? MINUTE AND (email = ?';
        $params = [self::WINDOW_MINUTES, $email];

        if ($ip !== null) {
            $sql .= ' OR ip = ?';
            $params[] = inet_pton($ip);
        }
        $sql .= ')';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    public static function record(PDO $pdo, string $email, ?string $ip): void
    {
        $stmt = $pdo->prepare('INSERT INTO login_attempts (email, ip) VALUES (?, ?)');
        $stmt->execute([$email, $ip === null ? '' : inet_pton($ip)]);
    }

    /** Opportunistisk gallring — inget schemalagt jobb behövs. */
    private static function purge(PDO $pdo): void
    {
        $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
    }

    /**
     * Körs efter en lyckad inloggning så att gamla felförsök inte lever
     * kvar och gör kontot spärrbenäget resten av fönstret.
     */
    public static function clear(PDO $pdo, string $email): void
    {
        $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE email = ?');
        $stmt->execute([$email]);
    }
}
