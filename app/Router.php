<?php
namespace App;

use PDO;

final class Router
{
    /**
     * keepSession = true för de endpoints som skriver till sessionen.
     * session_write_close() får inte köras före dem: inloggningen anropar
     * session_regenerate_id(true), vilket inte fungerar på en stängd session.
     */
    public static function routes(): array
    {
        return [
            ['POST',   '#^/api/session$#',              Handlers\SessionHandler::class,   'login',          'public', true],
            ['GET',    '#^/api/session$#',              Handlers\SessionHandler::class,   'me',             'user',   false],
            ['DELETE', '#^/api/session$#',              Handlers\SessionHandler::class,   'logout',         'user',   true],
            ['PUT',    '#^/api/session/password$#',     Handlers\SessionHandler::class,   'changePassword', 'user',   true],
            ['GET',    '#^/api/bootstrap$#',            Handlers\BootstrapHandler::class, 'index',          'user',   false],
            ['POST',   '#^/api/vacations$#',            Handlers\VacationHandler::class,  'create',         'user',   false],
            ['PUT',    '#^/api/vacations/([\w-]+)$#',   Handlers\VacationHandler::class,  'update',         'owner',  false],
            ['DELETE', '#^/api/vacations/([\w-]+)$#',   Handlers\VacationHandler::class,  'destroy',        'owner',  false],
            ['POST',   '#^/api/persons$#',              Handlers\PersonHandler::class,    'create',         'admin',  false],
            ['PUT',    '#^/api/persons/([\w-]+)$#',     Handlers\PersonHandler::class,    'update',         'admin',  false],
            ['DELETE', '#^/api/persons/([\w-]+)$#',     Handlers\PersonHandler::class,    'destroy',        'admin',  false],
            ['POST',   '#^/api/groups$#',               Handlers\GroupHandler::class,     'create',         'admin',  false],
            ['PUT',    '#^/api/groups/([\w-]+)$#',      Handlers\GroupHandler::class,     'update',         'admin',  false],
            ['DELETE', '#^/api/groups/([\w-]+)$#',      Handlers\GroupHandler::class,     'destroy',        'admin',  false],
        ];
    }

    public static function dispatch(PDO $pdo, string $method, string $path): void
    {
        foreach (self::routes() as [$m, $pattern, $class, $action, $access, $keepSession]) {
            if ($m !== $method || !preg_match($pattern, $path, $matches)) {
                continue;
            }

            // Ett enda try runt hela dispatchen. Vakter och valideringar kastar
            // HttpException i stället för att anropa exit, så att routern kan
            // omvandla dem till JSON — och så att PHPUnit överlever ett 403-svar.
            try {
                $user = Auth::currentUser($pdo);

                if ($access !== 'public' && $user === null) {
                    throw new HttpException('unauthenticated', 'Du måste logga in.', 401);
                }
                if (!$keepSession && PHP_SAPI !== 'cli') {
                    session_write_close();
                }
                if ($access === 'admin' && !$user['isAdmin']) {
                    throw new HttpException('forbidden', 'Kräver administratör.', 403);
                }
                if ($access === 'owner') {
                    Auth::requireOwner($pdo, $matches[1], $user);
                }

                (new $class($pdo))->$action($user, $matches[1] ?? null);
            } catch (HttpException $e) {
                Http::error($e->code, $e->getMessage(), $e->status);
            }

            return;
        }

        Http::error('not_found', 'Okänd resurs.', 404);
    }
}
