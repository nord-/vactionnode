<?php
namespace Tests;

use App\Sso\Base64Url;
use App\Handlers\SsoHandler;
use App\Http;
use Tests\Support\TokenFactory;

final class SsoHandlerTest extends ApiTestCase
{
    private const REDIRECT = 'https://semester.example.com/api/session/sso/callback';

    /** Anrop som den fejkade transporten tagit emot: [url, form]. */
    private array $calls = [];

    private function config(): array
    {
        return [
            'cookie_secure' => true,
            'sso' => [
                'tenant_id'     => TokenFactory::TENANT,
                'client_id'     => TokenFactory::CLIENT,
                'client_secret' => 'hemlis',
                'redirect_uri'  => self::REDIRECT,
            ],
        ];
    }

    /**
     * Fejkad transport mot Entra ID: tokenendpointen svarar med $idToken,
     * nyckelendpointen med testnyckelns JWKS.
     */
    private function handler(?callable $idToken = null): SsoHandler
    {
        $http = function (string $url, ?array $form = null) use ($idToken): array {
            $this->calls[] = [$url, $form];
            if (str_ends_with($url, '/oauth2/v2.0/token')) {
                return ['id_token' => $idToken === null ? '' : $idToken($form)];
            }
            if (str_ends_with($url, '/discovery/v2.0/keys')) {
                return TokenFactory::jwks();
            }
            throw new \LogicException("Oväntad URL: $url");
        };

        return new SsoHandler($this->pdo, $this->config(), $http);
    }

    /** Kör en handlermetod som routern hade gjort och returnerar svaret. */
    private function call(SsoHandler $handler, string $action, array $query = [], array $cookies = []): array
    {
        $_GET = $query;
        $_COOKIE = $cookies;
        $_SESSION = [];
        Http::resetTestState();

        ob_start();
        $handler->$action(null, null);
        $body = ob_get_clean();

        return [
            'status'   => Http::lastStatus(),
            'location' => Http::lastLocation(),
            'cookies'  => Http::lastCookies(),
            'json'     => json_decode($body ?: '{}', true) ?? [],
            'session'  => $_SESSION,
        ];
    }

    public function testOptionsReportMicrosoftWhenConfigured(): void
    {
        $res = $this->call($this->handler(), 'options');

        $this->assertSame(['microsoft' => true], $res['json']);
    }

    /** Den lokala config.php saknar sso-blocket — då finns ingen Microsoft-knapp. */
    public function testWithoutSsoConfigTheRoutesSayItIsOff(): void
    {
        $options = $this->request('GET', '/api/session/options');
        $this->assertSame(200, $options['status']);
        $this->assertSame(['microsoft' => false], $options['json']);

        $this->assertSame(404, $this->request('GET', '/api/session/sso')['status']);
        $this->assertSame(404, $this->request('GET', '/api/session/sso/callback')['status']);
    }

    public function testStartRedirectsToMicrosoftWithPkceAndState(): void
    {
        $res = $this->call($this->handler(), 'start');

        $this->assertSame(302, $res['status']);
        $url = parse_url($res['location']);
        parse_str($url['query'], $q);

        $this->assertSame(
            'https://login.microsoftonline.com/' . TokenFactory::TENANT . '/oauth2/v2.0/authorize',
            "{$url['scheme']}://{$url['host']}{$url['path']}"
        );
        $this->assertSame(TokenFactory::CLIENT, $q['client_id']);
        $this->assertSame(self::REDIRECT, $q['redirect_uri']);
        $this->assertSame('code', $q['response_type']);
        $this->assertSame('S256', $q['code_challenge_method']);
        $this->assertStringContainsString('openid', $q['scope']);

        // Transaktionen ligger i en egen kaka — sessionskakan är
        // SameSite=Strict och skickas inte med när Microsoft skickar
        // tillbaka webbläsaren.
        $tx = $res['cookies']['sso_tx'];
        $this->assertSame('Lax', $tx['options']['samesite']);
        $this->assertTrue($tx['options']['httponly']);
        $this->assertTrue($tx['options']['secure']);

        $saved = json_decode(base64_decode(strtr($tx['value'], '-_', '+/')), true);
        $this->assertSame($saved['state'], $q['state']);
        $this->assertSame($saved['nonce'], $q['nonce']);
        $this->assertSame(Base64Url::encode(hash('sha256', $saved['verifier'], true)), $q['code_challenge']);
    }

    /**
     * Startar ett flöde och returnerar [query som Microsoft skickar tillbaka,
     * kakorna webbläsaren har, nonce:n tokenet ska bära].
     */
    private function begin(): array
    {
        $start = $this->call($this->handler(), 'start');
        parse_str(parse_url($start['location'], PHP_URL_QUERY), $q);

        return [
            ['code' => 'the-code', 'state' => $q['state']],
            ['sso_tx' => $start['cookies']['sso_tx']['value']],
            $q['nonce'],
        ];
    }

    public function testCallbackSignsInAndRedirectsHome(): void
    {
        [$query, $cookies, $nonce] = $this->begin();
        $handler = $this->handler(
            static fn () => TokenFactory::token(TokenFactory::claims(['nonce' => $nonce]))
        );

        $res = $this->call($handler, 'callback', $query, $cookies);

        $this->assertSame(302, $res['status']);
        $this->assertSame('/', $res['location']);
        $this->assertArrayHasKey('user_id', $res['session']);

        // Koden byttes med PKCE-verifiern från kakan och appens hemlighet.
        $saved = json_decode(base64_decode(strtr($cookies['sso_tx'], '-_', '+/')), true);
        [, $form] = $this->calls[0];
        $this->assertSame('authorization_code', $form['grant_type']);
        $this->assertSame('the-code', $form['code']);
        $this->assertSame($saved['verifier'], $form['code_verifier']);
        $this->assertSame('hemlis', $form['client_secret']);
        $this->assertSame(self::REDIRECT, $form['redirect_uri']);

        // Transaktionskakan förbrukas — samma state kan inte användas igen.
        $this->assertSame('', $res['cookies']['sso_tx']['value']);

        $me = $this->request('GET', '/api/session', null, $res['session']['user_id']);
        $this->assertSame('anna@example.com', $me['json']['email']);
    }

    public function testUnexpectedErrorRedirectsInsteadOfAnswering500(): void
    {
        [$query, $cookies] = $this->begin();
        $handler = new SsoHandler($this->pdo, $this->config(), static function (): array {
            throw new \RuntimeException('nätverket föll');
        });

        $res = $this->call($handler, 'callback', $query, $cookies);

        $this->assertSame(302, $res['status']);
        $this->assertSame('/?sso_error=server_error', $res['location']);
        $this->assertArrayNotHasKey('user_id', $res['session']);
    }

    public function testMemberWhoseIdpIsTheOwnTenantSignsIn(): void
    {
        [$query, $cookies, $nonce] = $this->begin();
        $idp = 'https://sts.windows.net/' . TokenFactory::TENANT . '/';
        $handler = $this->handler(
            static fn () => TokenFactory::token(TokenFactory::claims(['nonce' => $nonce, 'idp' => $idp]))
        );

        $res = $this->call($handler, 'callback', $query, $cookies);

        $this->assertSame('/', $res['location']);
    }

    /**
     * @dataProvider failures
     */
    public function testFailedCallbackRedirectsWithReasonAndNoSession(callable $tamper, string $reason): void
    {
        [$query, $cookies, $nonce] = $this->begin();
        $this->pdo->exec("INSERT INTO users (id, name, email, sso_id)
                          VALUES ('x1', 'X', 'anna@example.com', 'ett-annat-oid')");
        $claims = ['nonce' => $nonce, 'email' => 'ny@example.com'];

        $tamper($query, $cookies, $claims);
        $handler = $this->handler(static fn () => TokenFactory::token(TokenFactory::claims($claims)));

        $res = $this->call($handler, 'callback', $query, $cookies);

        $this->assertSame(302, $res['status']);
        $this->assertSame('/?sso_error=' . $reason, $res['location']);
        $this->assertArrayNotHasKey('user_id', $res['session']);
    }

    public static function failures(): array
    {
        return [
            'fel state'           => [static function (&$q, &$c, &$t) { $q['state'] = 'förfalskad'; }, 'state_mismatch'],
            'kaka saknas'         => [static function (&$q, &$c, &$t) { $c = []; }, 'state_mismatch'],
            'användaren nekade'   => [static function (&$q, &$c, &$t) { $q = ['error' => 'access_denied', 'state' => $q['state']]; }, 'denied'],
            'fel nonce'           => [static function (&$q, &$c, &$t) { $t['nonce'] = 'annan'; }, 'invalid_token'],
            'e-post saknas'       => [static function (&$q, &$c, &$t) { $t['email'] = null; }, 'no_email'],
            'kontokonflikt'       => [static function (&$q, &$c, &$t) { $t['email'] = 'anna@example.com'; }, 'account_conflict'],
            // idp skiljer sig från issuern bara när kontot hör hemma i en
            // annan tenant eller är ett personligt Microsoft-konto — en gäst.
            'gäst från annan tenant' => [static function (&$q, &$c, &$t) { $t['idp'] = 'https://sts.windows.net/99999999-0000-0000-0000-000000000000/'; }, 'guest'],
            'gäst enligt acct'    => [static function (&$q, &$c, &$t) { $t['acct'] = 1; }, 'guest'],
            'acct saknas'         => [static function (&$q, &$c, &$t) { $t['acct'] = null; }, 'acct_missing'],
            'gäst med MSA'        => [static function (&$q, &$c, &$t) { $t['idp'] = 'live.com'; }, 'guest'],
        ];
    }
}
