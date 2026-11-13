<?php
namespace Tests;

final class GuardMatrixTest extends ApiTestCase
{
    private function seed(): void
    {
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash, is_admin) VALUES
            ('g-owner', 'Anna', 'ga@example.com', 'x', 0),
            ('g-other', 'Bo',   'gb@example.com', 'x', 0),
            ('g-admin', 'Cia',  'gc@example.com', 'x', 1)");
        $this->pdo->exec("INSERT INTO vacations (id, user_id, start_date, end_date)
                          VALUES ('g-vac', 'g-owner', '2026-06-16', '2026-06-20')");
        $this->pdo->exec("INSERT INTO teams (id, name) VALUES ('g-team', 'Team A')");
    }

    /**
     * @dataProvider matrix
     */
    public function testGuardMatrix(string $method, string $path, ?array $body,
                                    ?string $as, int $expected): void
    {
        $this->seed();

        $res = $this->request($method, $path, $body, $as);

        $this->assertSame($expected, $res['status'], "$method $path som " . ($as ?? 'anonym'));
    }

    public static function matrix(): array
    {
        $dates = ['start' => '2026-06-16', 'end' => '2026-06-20'];
        $name  = ['name' => 'Nytt'];

        return [
            'bootstrap anonym'     => ['GET',    '/api/bootstrap',        null,   null,      401],
            'bootstrap ej ägare'   => ['GET',    '/api/bootstrap',        null,   'g-other', 200],
            'bootstrap admin'      => ['GET',    '/api/bootstrap',        null,   'g-admin', 200],
            'lösenord anonym'      => ['PUT',    '/api/session/password', [],     null,      401],
            'skapa semester anon'  => ['POST',   '/api/vacations',        $dates, null,      401],
            'skapa semester egen'  => ['POST',   '/api/vacations',        $dates, 'g-other', 201],
            'ändra annans'         => ['PUT',    '/api/vacations/g-vac',  $dates, 'g-other', 403],
            'ändra egen'           => ['PUT',    '/api/vacations/g-vac',  $dates, 'g-owner', 200],
            'ändra som admin'      => ['PUT',    '/api/vacations/g-vac',  $dates, 'g-admin', 200],
            'ta bort annans'       => ['DELETE', '/api/vacations/g-vac',  null,   'g-other', 403],
            'personer ej admin'    => ['PUT',    '/api/persons/g-other',  $name,  'g-other', 403],
            'personer anonym'      => ['POST',   '/api/persons',          $name,  null,      401],
            'grupper ej admin'     => ['PUT',    '/api/groups/g-team',    $name,  'g-owner', 403],
            'grupper anonym'       => ['DELETE', '/api/groups/g-team',    null,   null,      401],
            // Microsoft-inloggningen är publik per definition. start och
            // callback ligger inte här: deras status beror på den lokala
            // config.php (404 utan sso-block), se testSsoRoutesNeedNoSession.
            'inloggningsval anon'  => ['GET',    '/api/session/options',  null,   null,      200],
        ];
    }

    /**
     * Vakten ska släppa igenom en anonym request till SSO-vägarna, vad
     * handlern sedan svarar. Statusen beror på om config.php har ett
     * sso-block, så testet kräver bara att svaret inte är en vaktavvisning.
     *
     * @dataProvider ssoRoutes
     */
    public function testSsoRoutesNeedNoSession(string $path): void
    {
        $res = $this->request('GET', $path);

        $this->assertNotContains($res['status'], [0, 401, 403], "GET $path anonym");
    }

    public static function ssoRoutes(): array
    {
        return [
            'start'    => ['/api/session/sso'],
            'callback' => ['/api/session/sso/callback'],
        ];
    }
}
