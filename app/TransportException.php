<?php
namespace App;

use RuntimeException;

/** Ett HTTP-anrop via Transport misslyckades: fel status eller ett svar som inte är JSON. */
final class TransportException extends RuntimeException
{
}
