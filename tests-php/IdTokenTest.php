<?php
namespace Tests;

use App\Sso\Base64Url;
use App\Sso\IdToken;
use App\Sso\SsoException;
use PHPUnit\Framework\TestCase;
use Tests\Support\TokenFactory;

final class IdTokenTest extends TestCase
{
    private function expect(array $overrides = []): array
    {
        return array_merge([
            'issuer'   => TokenFactory::issuer(),
            'audience' => TokenFactory::CLIENT,
            'tenant'   => TokenFactory::TENANT,
            'nonce'    => 'the-nonce',
        ], $overrides);
    }

    public function testValidTokenReturnsItsClaims(): void
    {
        $token = TokenFactory::token(TokenFactory::claims());

        $claims = IdToken::verify($token, TokenFactory::jwks(), $this->expect());

        $this->assertSame('0f0f0f0f-1111-2222-3333-444444444444', $claims['oid']);
        $this->assertSame('Anna@Example.com', $claims['email']);
    }

    /**
     * @dataProvider rejected
     */
    public function testRejectsInvalidToken(callable $makeToken, array $expectOverrides): void
    {
        $this->expectException(SsoException::class);

        IdToken::verify($makeToken(), TokenFactory::jwks(), $this->expect($expectOverrides));
    }

    public static function rejected(): array
    {
        $with = static fn (array $claims, array $header = []) =>
            static fn () => TokenFactory::token(TokenFactory::claims($claims), $header);

        return [
            'fel issuer'         => [$with(['iss' => 'https://login.microsoftonline.com/annan/v2.0']), []],
            'fel audience'       => [$with(['aud' => 'en-annan-app']), []],
            'fel tenant'         => [$with(['tid' => '99999999-2222-3333-4444-555555555555']), []],
            'fel nonce'          => [$with([]), ['nonce' => 'annan-nonce']],
            'saknad nonce'       => [$with(['nonce' => null]), []],
            'utgånget'           => [$with(['exp' => time() - 3600]), []],
            'inte giltigt än'    => [$with(['nbf' => time() + 3600]), []],
            'saknar oid'         => [$with(['oid' => null]), []],
            'okänd kid'          => [$with([], ['kid' => 'annan-nyckel']), []],
            'alg none'           => [$with([], ['alg' => 'none']), []],
            'manipulerad payload' => [static function () {
                [$h, , $s] = explode('.', TokenFactory::token(TokenFactory::claims()));
                $p = Base64Url::encode(json_encode(TokenFactory::claims(['oid' => 'någon-annan'])));
                return "$h.$p.$s";
            }, []],
            'skräp'              => [static fn () => 'inte.ett-token', []],
        ];
    }
}
