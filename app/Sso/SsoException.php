<?php
namespace App\Sso;

use RuntimeException;

/**
 * Microsoft-inloggningen avbröts. $reason är en kort kod som skickas till
 * frontend i ?sso_error= — aldrig meddelandet, som kan innehålla detaljer
 * om tokenet som inte hör hemma i en URL.
 */
final class SsoException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
