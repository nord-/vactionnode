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
    public function __construct(
        public readonly string $code,
        string $message,
        public readonly int $status
    ) {
        parent::__construct($message);
    }
}
