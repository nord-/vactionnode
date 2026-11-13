<?php
namespace App\Sso;

/**
 * Validerar ett id-token från Entra ID. Bara RS256 accepteras, och nyckeln
 * hämtas ur JWKS:ens x5c-certifikat via kid — openssl läser ett certifikat
 * direkt, så ingen JWT-dependency behövs (vendor/ deployas inte).
 */
final class IdToken
{
    /** Tillåten klockskillnad mot Entra ID, i sekunder. */
    private const LEEWAY = 300;

    /**
     * @param array $expect issuer, audience, tenant, nonce och valfritt now
     * @return array tokenets claims
     * @throws SsoException
     */
    public static function verify(string $jwt, array $jwks, array $expect): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new SsoException('invalid_token', 'Tokenet har fel format.');
        }
        [$h, $p, $s] = $parts;

        $header = json_decode(Base64Url::decode($h), true);
        $claims = json_decode(Base64Url::decode($p), true);
        if (!is_array($header) || !is_array($claims)) {
            throw new SsoException('invalid_token', 'Tokenet går inte att avkoda.');
        }

        // alg måste kontrolleras före signaturen: annars kan ett token med
        // alg=none eller ett HMAC-alg luras igenom av en slarvig verifiering.
        if (($header['alg'] ?? null) !== 'RS256') {
            throw new SsoException('invalid_token', 'Otillåten signaturalgoritm.');
        }

        $key = self::publicKey($jwks, (string) ($header['kid'] ?? ''));
        if (openssl_verify("$h.$p", Base64Url::decode($s), $key, OPENSSL_ALGO_SHA256) !== 1) {
            throw new SsoException('invalid_token', 'Signaturen stämmer inte.');
        }

        self::checkClaims($claims, $expect);

        return $claims;
    }

    private static function checkClaims(array $claims, array $expect): void
    {
        $now = $expect['now'] ?? time();

        $checks = [
            'iss'   => $expect['issuer'],
            'aud'   => $expect['audience'],
            'tid'   => $expect['tenant'],
            'nonce' => $expect['nonce'],
        ];
        foreach ($checks as $claim => $expected) {
            if (!isset($claims[$claim]) || !hash_equals((string) $expected, (string) $claims[$claim])) {
                throw new SsoException('invalid_token', "Claimet $claim stämmer inte.");
            }
        }

        if (!isset($claims['exp']) || $claims['exp'] + self::LEEWAY < $now) {
            throw new SsoException('invalid_token', 'Tokenet har gått ut.');
        }
        if (isset($claims['nbf']) && $claims['nbf'] - self::LEEWAY > $now) {
            throw new SsoException('invalid_token', 'Tokenet är inte giltigt än.');
        }
        if (empty($claims['oid'])) {
            throw new SsoException('invalid_token', 'Tokenet saknar oid.');
        }
    }

    /** @return \OpenSSLAsymmetricKey */
    private static function publicKey(array $jwks, string $kid)
    {
        foreach ($jwks['keys'] ?? [] as $jwk) {
            if (($jwk['kid'] ?? null) !== $kid || empty($jwk['x5c'][0])) {
                continue;
            }
            $pem = "-----BEGIN CERTIFICATE-----\n"
                 . chunk_split($jwk['x5c'][0], 64, "\n")
                 . "-----END CERTIFICATE-----\n";
            $key = openssl_pkey_get_public($pem);
            if ($key !== false) {
                return $key;
            }
        }

        throw new SsoException('invalid_token', 'Okänd signeringsnyckel.');
    }
}
