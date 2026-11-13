<?php
namespace App\Sso;

final class SsoConfig
{
    private const KEYS = ['tenant_id', 'client_id', 'client_secret', 'redirect_uri'];

    /** SSO är på när alla fyra värdena i config.php:s sso-block är ifyllda. */
    public static function enabled(array $config): bool
    {
        $sso = $config['sso'] ?? [];
        foreach (self::KEYS as $key) {
            if (empty($sso[$key])) {
                return false;
            }
        }

        return true;
    }
}
