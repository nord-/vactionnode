<?php
namespace App\Handlers;

use App\Config;
use App\Http;
use App\HttpException;
use App\Sso\Base64Url;
use App\Sso\IdToken;
use App\Sso\SsoAccounts;
use App\Sso\SsoConfig;
use App\Sso\SsoException;
use App\Sso\Transport;
use Closure;
use PDO;

/**
 * Microsoft Entra ID-inloggning: authorization code flow med PKCE.
 *
 * start skickar webbläsaren till Microsoft, callback tar emot koden, byter
 * den mot ett id-token, validerar tokenet och loggar in kontot som oid:t
 * pekar ut. Fel slutar alltid i en redirect till /?sso_error=<kod>, aldrig
 * i JSON — användaren står på en sida som webbläsaren navigerat till.
 */
final class SsoHandler
{
    private const AUTHORITY = 'https://login.microsoftonline.com/';
    private const TX_COOKIE = 'sso_tx';
    private const TX_PATH   = '/api/session/sso';
    private const TX_TTL    = 600;
    private const SCOPE     = 'openid profile email';

    private array $config;
    private Closure $http;

    /**
     * $config och $http finns för testerna. Routern skickar bara $pdo, och
     * då läses config.php och den riktiga transporten används.
     */
    public function __construct(private PDO $pdo, ?array $config = null, ?callable $http = null)
    {
        $this->config = $config ?? Config::load();
        $this->http = Closure::fromCallable($http ?? [Transport::class, 'request']);
    }

    /** Inloggningssidan frågar om Microsoft-knappen ska visas. */
    public function options(?array $user, ?string $id = null): void
    {
        Http::json(['microsoft' => SsoConfig::enabled($this->config)]);
    }

    public function start(?array $user, ?string $id = null): void
    {
        $sso = $this->requireSso();

        $tx = [
            'state'    => self::random(),
            'nonce'    => self::random(),
            'verifier' => self::random(),
        ];
        $this->setTxCookie(Base64Url::encode(json_encode($tx)), time() + self::TX_TTL);

        Http::redirect($this->endpoint('oauth2/v2.0/authorize') . '?' . http_build_query([
            'client_id'             => $sso['client_id'],
            'response_type'         => 'code',
            'redirect_uri'          => $sso['redirect_uri'],
            'response_mode'         => 'query',
            'scope'                 => self::SCOPE,
            'state'                 => $tx['state'],
            'nonce'                 => $tx['nonce'],
            'code_challenge'        => Base64Url::encode(hash('sha256', $tx['verifier'], true)),
            'code_challenge_method' => 'S256',
        ]));
    }

    public function callback(?array $user, ?string $id = null): void
    {
        // 404 före allt annat: transaktionskakan ska inte förbrukas när
        // SSO är avstängt.
        $this->requireSso();
        $tx = $this->takeTx();

        try {
            $userId = $this->complete($tx);
        } catch (SsoException $e) {
            error_log('Microsoft-inloggning avbruten: ' . $e->getMessage());
            Http::redirect('/?sso_error=' . rawurlencode($e->reason));
            return;
        } catch (\Throwable $e) {
            // Aldrig JSON här: webbläsaren har navigerat hit. Samma
            // loggformat som index.php, som undviker spårningen.
            error_log(sprintf('%s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
            Http::redirect('/?sso_error=server_error');
            return;
        }

        // Guardad som i SessionHandler::login: PHPUnit har ingen aktiv session.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $userId;

        Http::redirect('/');
    }

    /** @throws SsoException */
    private function complete(?array $tx): string
    {
        if (isset($_GET['error'])) {
            throw new SsoException('denied', 'Microsoft svarade: ' . (string) $_GET['error']);
        }

        $state = (string) ($_GET['state'] ?? '');
        if ($tx === null || $state === '' || !hash_equals($tx['state'], $state)) {
            throw new SsoException('state_mismatch', 'state saknas eller stämmer inte.');
        }

        $sso = $this->requireSso();
        $tokens = ($this->http)($this->endpoint('oauth2/v2.0/token'), [
            'grant_type'    => 'authorization_code',
            'client_id'     => $sso['client_id'],
            'client_secret' => $sso['client_secret'],
            'code'          => (string) ($_GET['code'] ?? ''),
            'redirect_uri'  => $sso['redirect_uri'],
            'code_verifier' => $tx['verifier'],
            'scope'         => self::SCOPE,
        ]);

        $claims = IdToken::verify(
            (string) ($tokens['id_token'] ?? ''),
            ($this->http)($this->endpoint('discovery/v2.0/keys')),
            [
                'issuer'   => self::AUTHORITY . $sso['tenant_id'] . '/v2.0',
                'audience' => $sso['client_id'],
                'tenant'   => $sso['tenant_id'],
                'nonce'    => $tx['nonce'],
            ]
        );

        // Gäster nekas (beslut 2026-11-12). Deras e-post är overifierad och
        // hade kunnat länka — och rensa lösenordet på — någon annans konto.
        // acct är ett valfritt claim: 0 för medlem, 1 för gäst. Det måste
        // finnas, annars nekas inloggningen — idp är ingen ersättare, för
        // Entra ID lovar den inte för alla gäster och dess frånvaro betyder
        // att iss gäller, alltså samma sak som en medlem.
        $acct = $claims['acct'] ?? null;
        if ($acct === null) {
            throw new SsoException('acct_missing', 'Tokenet saknar claimet acct. Lägg till det som valfritt claim på id-tokenet i app-registreringen.');
        }
        if ((string) $acct !== '0') {
            throw new SsoException('guest', 'Gästkonto nekas (acct=' . (string) $acct . ').');
        }

        // Andra linjen: idp skiljer sig från issuern när kontot inte hör
        // hemma i issuerns tenant, en gäst från en annan tenant eller ett
        // personligt konto.
        $idp = $claims['idp'] ?? null;
        if ($idp !== null && !in_array($idp, [$claims['iss'], self::memberIdp($sso['tenant_id'])], true)) {
            throw new SsoException('guest', "Gästkonto från $idp nekas.");
        }

        // email är ett valfritt claim i Entra ID; preferred_username (UPN)
        // är i praktiken e-postadressen när det saknas.
        $email = (string) ($claims['email'] ?? $claims['preferred_username'] ?? '');
        if (filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
            throw new SsoException('no_email', 'Tokenet saknar en e-postadress.');
        }

        return SsoAccounts::resolve($this->pdo, $claims['oid'], $email, (string) ($claims['name'] ?? ''));
    }

    /** Läser och förbrukar transaktionskakan. */
    private function takeTx(): ?array
    {
        $raw = (string) ($_COOKIE[self::TX_COOKIE] ?? '');
        $this->setTxCookie('', time() - 3600);

        $tx = json_decode(Base64Url::decode($raw), true);

        return is_array($tx) && isset($tx['state'], $tx['nonce'], $tx['verifier'])
            && is_string($tx['state']) && is_string($tx['nonce']) && is_string($tx['verifier'])
            ? $tx
            : null;
    }

    /**
     * Transaktionen (state, nonce, PKCE-verifier) ligger i en egen kaka med
     * SameSite=Lax. Sessionskakan är Strict och skickas därför inte med på
     * navigeringen tillbaka från login.microsoftonline.com — state hade
     * aldrig gått att jämföra om den legat i sessionen.
     */
    private function setTxCookie(string $value, int $expires): void
    {
        Http::cookie(self::TX_COOKIE, $value, [
            'expires'  => $expires,
            'path'     => self::TX_PATH,
            'secure'   => $this->config['cookie_secure'] ?? true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /** @throws HttpException 404 när SSO inte är konfigurerat */
    private function requireSso(): array
    {
        if (!SsoConfig::enabled($this->config)) {
            throw new HttpException('not_found', 'Microsoft-inloggning är inte aktiverad.', 404);
        }

        return $this->config['sso'];
    }

    private function endpoint(string $path): string
    {
        return self::AUTHORITY . rawurlencode($this->config['sso']['tenant_id']) . '/' . $path;
    }

    /** v1-formen av den egna tenantens issuer, som idp kan bära för medlemmar. */
    private static function memberIdp(string $tenantId): string
    {
        return 'https://sts.windows.net/' . $tenantId . '/';
    }

    private static function random(): string
    {
        return Base64Url::encode(random_bytes(32));
    }
}
