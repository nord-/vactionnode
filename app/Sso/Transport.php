<?php
namespace App\Sso;

/**
 * HTTPS-anrop mot Entra ID. Går via PHP:s streams i stället för curl — curl
 * är ett tillägg som måste slås på i Web Stations PHP-profil, medan
 * streams bara kräver openssl, som inloggningen behöver ändå.
 */
final class Transport
{
    private const TIMEOUT = 10;

    /**
     * GET när $form är null, annars POST som application/x-www-form-urlencoded.
     *
     * @return array det avkodade JSON-svaret
     * @throws SsoException
     */
    public static function request(string $url, ?array $form = null): array
    {
        $http = [
            'method'        => $form === null ? 'GET' : 'POST',
            'timeout'       => self::TIMEOUT,
            'ignore_errors' => true,
        ];
        if ($form !== null) {
            $http['header']  = "Content-Type: application/x-www-form-urlencoded\r\n";
            $http['content'] = http_build_query($form);
        }

        $context = stream_context_create([
            'http' => $http,
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $context);
        $status = self::status($http_response_header ?? []);

        $data = is_string($body) ? json_decode($body, true) : null;
        if ($status !== 200 || !is_array($data)) {
            // Entra ID:s felsvar har error och error_description; tokenet
            // eller hemligheten finns aldrig i dem, så de är säkra att logga.
            $detail = is_array($data) ? ($data['error_description'] ?? $data['error'] ?? '') : '';
            throw new SsoException('provider_error', "Entra ID svarade $status på $url. $detail");
        }

        return $data;
    }

    private static function status(array $headers): int
    {
        return preg_match('#^HTTP/\S+\s+(\d{3})#', $headers[0] ?? '', $m) ? (int) $m[1] : 0;
    }
}
