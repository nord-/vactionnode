<?php
namespace Tests;

use App\Http;
use App\Router;

/**
 * Kör routern i samma process som testet. Sessionen simuleras genom att
 * $_SESSION sätts direkt — det håller testerna snabba och oberoende av
 * en körande webbserver.
 */
abstract class ApiTestCase extends DbTestCase
{
    // Test-seam för regressionstestet av Http::resetTestState(): sätts till
    // true av ett enda test för att simulera en handlerväg som returnerar
    // utan att svara. Produktionsroutern har inget motsvarande — det är
    // medvetet, riktig routing ska aldrig bära på testkrokar.
    protected bool $skipDispatch = false;

    protected function request(
        string $method,
        string $path,
        ?array $body = null,
        ?string $asUserId = null
    ): array {
        $_SESSION = $asUserId === null ? [] : ['user_id' => $asUserId];
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        // Routingmönstren är ankrade med $, så en query-sträng i sökvägen
        // matchar aldrig. Den måste bort ur sökvägen och in i $_GET, precis
        // som front controllern gör med parse_url().
        $parts = parse_url($path);
        $_GET = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $_GET);
        }

        // Nollställ innan dispatch: annars kan Http::lastStatus() av misstag
        // rapportera FÖREGÅENDE tests statuskod om den här requesten går en
        // väg som glömmer att svara.
        Http::resetTestState();
        Http::setTestBody($body === null ? '' : json_encode($body));

        ob_start();
        $this->dispatch($method, $parts['path']);
        $out = ob_get_clean();

        return [
            'status' => Http::lastStatus(),
            'json'   => json_decode($out ?: '{}', true) ?? [],
        ];
    }

    /**
     * Egen metod bara så att ett enda test kan hoppa över den och simulera
     * en handler som returnerar utan att svara — utan att routingtabellen
     * behöver bära på ett testfejkat anrop.
     */
    protected function dispatch(string $method, string $path): void
    {
        if ($this->skipDispatch) {
            return;
        }

        Router::dispatch($this->pdo, $method, $path);
    }
}
