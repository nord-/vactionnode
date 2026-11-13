<?php
namespace App\Sso;

/** base64url utan utfyllnad (RFC 4648 §5), som JWT och PKCE använder. */
final class Base64Url
{
    public static function encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /** Ogiltig indata ger en tom sträng, aldrig false. */
    public static function decode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}
