<?php
namespace App;

use RuntimeException;

/**
 * Avbryter en request med en given statuskod. Router::dispatch() fångar den
 * och gör den till ett JSON-felsvar.
 *
 * Varför ett undantag och inte exit: ApiTestCase kör routern i samma process
 * som PHPUnit. Ett exit i en vakt eller en validering hade avslutat hela
 * testsviten vid första 403:an, utan rapport och utan resterande tester.
 */
final class HttpException extends RuntimeException
{
    // $code kan inte deklareras med typ eller readonly här: den inbyggda
    // Exception-klassen äger redan en otypad, icke-readonly $code-egenskap,
    // och PHP 8.2 tillåter inte att en subklass gör den strikare. Se
    // fatalen "Cannot redeclare non-readonly property Exception::$code as
    // readonly" om detta någonsin återinförs.
    public $code;

    public function __construct(
        string $code,
        string $message,
        public readonly int $status
    ) {
        parent::__construct($message);
        $this->code = $code;
    }
}
