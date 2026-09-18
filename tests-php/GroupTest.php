<?php
namespace Tests;

final class GroupTest extends ApiTestCase
{
    private function seedAdmin(): void
    {
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash, is_admin)
                          VALUES ('gr-admin', 'Admin', 'gr@example.com', 'x', 1)");
    }

    public function testCreateReturnsGroupWithId(): void
    {
        $this->seedAdmin();

        $res = $this->request('POST', '/api/groups', ['name' => 'Team A'], 'gr-admin');

        $this->assertSame(201, $res['status']);
        $this->assertSame('Team A', $res['json']['name']);
        $this->assertNotEmpty($res['json']['id']);
    }

    public function testDuplicateNameIsRejected(): void
    {
        $this->seedAdmin();
        $this->request('POST', '/api/groups', ['name' => 'Team A'], 'gr-admin');

        $res = $this->request('POST', '/api/groups', ['name' => 'Team A'], 'gr-admin');

        $this->assertSame(422, $res['status']);
    }

    public function testDeletingGroupUngroupsMembersInsteadOfDeletingThem(): void
    {
        $this->seedAdmin();
        $this->pdo->exec("INSERT INTO teams (id, name) VALUES ('gr1', 'Team A')");
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash, team_id)
                          VALUES ('gr-u1', 'Anna', 'anna@example.com', 'x', 'gr1')");

        $res = $this->request('DELETE', '/api/groups/gr1', null, 'gr-admin');

        $this->assertSame(200, $res['status']);
        $this->assertSame(1, (int) $this->pdo
            ->query("SELECT COUNT(*) FROM users WHERE id = 'gr-u1'")->fetchColumn());
        $this->assertNull($this->pdo
            ->query("SELECT team_id FROM users WHERE id = 'gr-u1'")->fetchColumn());
    }

    /**
     * Diskriminerande test för PDO::MYSQL_ATTR_FOUND_ROWS: update() tolkar
     * rowCount() === 0 som "gruppen finns inte" och svarar 404. Utan
     * FOUND_ROWS räknar rowCount() efter UPDATE ÄNDRADE rader, inte
     * MATCHADE rader — att döpa om en grupp till dess nuvarande namn
     * ändrar noll rader och skulle då ge ett falskt 404 för ett giltigt
     * anrop. Med FOUND_ROWS räknas den matchade raden och svaret blir 200.
     */
    public function testRenamingGroupToItsCurrentNameReturns200(): void
    {
        $this->seedAdmin();
        $this->pdo->exec("INSERT INTO teams (id, name) VALUES ('gr2', 'Team B')");

        $res = $this->request('PUT', '/api/groups/gr2', ['name' => 'Team B'], 'gr-admin');

        $this->assertSame(200, $res['status']);
    }

    public function testUpdatingNonExistentGroupReturns404(): void
    {
        $this->seedAdmin();

        $res = $this->request('PUT', '/api/groups/does-not-exist', ['name' => 'Vad som helst'], 'gr-admin');

        $this->assertSame(404, $res['status']);
    }

    /**
     * Testar backstoppet direkt via reflektion, med ett riktigt
     * PDOException-objekt som en faktisk villkorskränkning mot databasen
     * (inte fabricerat) — samma metod som PersonTest använder. Det här är
     * det enda sättet att pröva backstoppet i sig snarare än den vanliga
     * före-kontrollen: create()/update() delar samma anslutning som testet
     * i ApiTestCase, så en dubblett som testet sätter in innan requesten
     * alltid syns av före-kontrollen — det är en genuin kapplöpning mellan
     * två skilda anslutningar som krävs för att nå backstoppet via HTTP,
     * och det testas inte här.
     */
    public function testIntegrityViolationMapsDuplicateNameTo422(): void
    {
        $this->pdo->exec("INSERT INTO teams (id, name) VALUES ('gr3', 'Team C')");

        try {
            $this->pdo->exec("INSERT INTO teams (id, name) VALUES ('gr3dup', 'Team C')");
            $this->fail('Förväntade UNIQUE-krock på namn.');
        } catch (\PDOException $duplicateName) {
        }

        $handler = new \App\Handlers\GroupHandler($this->pdo);
        $method  = new \ReflectionMethod($handler, 'throwForIntegrityViolation');
        $method->setAccessible(true);

        try {
            $method->invoke($handler, $duplicateName);
            $this->fail('Förväntade HttpException.');
        } catch (\App\HttpException $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame('Gruppnamnet används redan.', $e->getMessage());
        }
    }
}
