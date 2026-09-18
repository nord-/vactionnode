<?php
namespace Tests;

final class BootstrapTest extends ApiTestCase
{
    private function seed(): void
    {
        $this->pdo->exec("INSERT INTO teams (id, name) VALUES ('t1', 'Team A')");
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash, team_id)
                          VALUES ('b1', 'Anna', 'anna@example.com', 'x', 't1')");
        // Börjar före intervallet, slutar inuti det.
        $this->pdo->exec("INSERT INTO vacations (id, user_id, start_date, end_date)
                          VALUES ('v1', 'b1', '2026-05-25', '2026-06-03')");
        // Helt utanför intervallet.
        $this->pdo->exec("INSERT INTO vacations (id, user_id, start_date, end_date)
                          VALUES ('v2', 'b1', '2026-09-01', '2026-09-05')");
    }

    public function testResponseUsesFrontendVocabulary(): void
    {
        $this->seed();

        $res = $this->request('GET', '/api/bootstrap?from=2026-06-01&to=2026-06-30', null, 'b1');

        $this->assertSame(200, $res['status']);
        $this->assertSame('b1', $res['json']['me']['id']);
        $this->assertSame('t1', $res['json']['persons'][0]['groupId']);
        $this->assertSame('b1', $res['json']['vacations'][0]['personId']);
        $this->assertSame('2026-05-25', $res['json']['vacations'][0]['start']);
        $this->assertSame('2026-06-03', $res['json']['vacations'][0]['end']);
    }

    public function testFilterIsOverlapNotContainment(): void
    {
        $this->seed();

        $res = $this->request('GET', '/api/bootstrap?from=2026-06-01&to=2026-06-30', null, 'b1');

        $ids = array_column($res['json']['vacations'], 'id');
        $this->assertContains('v1', $ids, 'Överlappande semester måste komma med.');
        $this->assertNotContains('v2', $ids);
    }

    public function testEmptyDatasetReturnsEmptyListsNotError(): void
    {
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash)
                          VALUES ('b2', 'Bo', 'bo@example.com', 'x')");

        $res = $this->request('GET', '/api/bootstrap?from=2026-06-01&to=2026-06-30', null, 'b2');

        $this->assertSame(200, $res['status']);
        $this->assertSame([], $res['json']['groups']);
        $this->assertSame([], $res['json']['vacations']);
    }

    /**
     * ?from[]=x når PHP som en array. Skickad rakt in i execute() hade
     * det kastat en PDOException och blivit ett 500 i stället för ett
     * hjälpsamt 422.
     */
    public function testArrayQueryParameterReturns422InsteadOf500(): void
    {
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash)
                          VALUES ('b3', 'Cia', 'cia@example.com', 'x')");

        $res = $this->request('GET', '/api/bootstrap?from[]=x', null, 'b3');

        $this->assertSame(422, $res['status']);
    }

    public function testMalformedDateStringReturns422(): void
    {
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash)
                          VALUES ('b4', 'Dag', 'dag@example.com', 'x')");

        $res = $this->request('GET', '/api/bootstrap?from=not-a-date', null, 'b4');

        $this->assertSame(422, $res['status']);
    }
}
