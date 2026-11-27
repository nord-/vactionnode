<?php
namespace App\Holidays;

use RuntimeException;

/** Helgdagskällan svarade inte, eller svaret hade fel form. Inget har sparats. */
final class UpstreamException extends RuntimeException
{
}
