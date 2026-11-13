<?php
namespace Tests\Support;

use App\Sso\Base64Url;

/**
 * Tillverkar RS256-signerade id-tokens och en matchande JWKS med x5c, så att
 * valideringen kan testas utan Entra ID. Nyckelparet genereras en gång per
 * process — RSA-generering är långsam nog att märkas i varje test annars.
 */
final class TokenFactory
{
    public const TENANT = '11111111-2222-3333-4444-555555555555';
    public const CLIENT = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    public const KID    = 'test-key';

    private static ?array $keys = null;

    public static function issuer(): string
    {
        return 'https://login.microsoftonline.com/' . self::TENANT . '/v2.0';
    }

    /** Standardclaims för en giltig token; $overrides ersätter eller tar bort (null). */
    public static function claims(array $overrides = []): array
    {
        $now = time();
        $claims = array_merge([
            'iss'   => self::issuer(),
            'aud'   => self::CLIENT,
            'tid'   => self::TENANT,
            'oid'   => '0f0f0f0f-1111-2222-3333-444444444444',
            'sub'   => 'pairwise-subject',
            'email' => 'Anna@Example.com',
            'name'  => 'Anna Andersson',
            'acct'  => 0,
            'nonce' => 'the-nonce',
            'iat'   => $now,
            'nbf'   => $now,
            'exp'   => $now + 3600,
        ], $overrides);

        return array_filter($claims, static fn ($v) => $v !== null);
    }

    public static function token(array $claims, array $header = []): string
    {
        $header = array_merge(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => self::KID], $header);
        $input  = Base64Url::encode(json_encode($header)) . '.' . Base64Url::encode(json_encode($claims));
        openssl_sign($input, $signature, self::keys()['private'], OPENSSL_ALGO_SHA256);

        return $input . '.' . Base64Url::encode($signature);
    }

    public static function jwks(string $kid = self::KID): array
    {
        return ['keys' => [[
            'kty' => 'RSA',
            'use' => 'sig',
            'kid' => $kid,
            'x5c' => [self::keys()['cert']],
        ]]];
    }

    private static function keys(): array
    {
        if (self::$keys === null) {
            $private = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            $csr  = openssl_csr_new(['commonName' => 'test'], $private, ['digest_alg' => 'sha256']);
            $cert = openssl_csr_sign($csr, null, $private, 1, ['digest_alg' => 'sha256']);
            openssl_x509_export($cert, $pem);

            // x5c i en JWKS är DER i vanlig base64, utan PEM-rader.
            $body = preg_replace('/-----[^-]+-----|\s/', '', $pem);
            self::$keys = ['private' => $private, 'cert' => $body];
        }

        return self::$keys;
    }
}
