<?php
namespace Tests;

final class VacationTest extends ApiTestCase
{
    private function seedTwoUsers(): void
    {
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash, is_admin) VALUES
            ('owner', 'Anna', 'anna@example.com', 'x', 0),
            ('other', 'Bo',   'bo@example.com',   'x', 0),
            ('admin', 'Cia',  'cia@example.com',  'x', 1)");
        $this->pdo->exec("INSERT INTO vacations (id, user_id, start_date, end_date)
                          VALUES ('vac1', 'owner', '2026-06-16', '2026-06-20')");
    }

    public function testCreateUsesSessionUserForNonAdmin(): void
    {
        $this->seedTwoUsers();

        $res = $this->request('POST', '/api/vacations',
            ['personId' => 'other', 'start' => '2026-07-01', 'end' => '2026-07-05'], 'owner');

        $this->assertSame(201, $res['status']);
        $this->assertSame('owner', $res['json']['personId'], 'personId får inte gå att förfalska.');
    }

    public function testAdminCanCreateForSomeoneElse(): void
    {
        $this->seedTwoUsers();

        $res = $this->request('POST', '/api/vacations',
            ['personId' => 'other', 'start' => '2026-07-01', 'end' => '2026-07-05'], 'admin');

        $this->assertSame(201, $res['status']);
        $this->assertSame('other', $res['json']['personId']);
    }

    public function testUnchangedDatesStillReturnOk(): void
    {
        $this->seedTwoUsers();

        $res = $this->request('PUT', '/api/vacations/vac1',
            ['start' => '2026-06-16', 'end' => '2026-06-20'], 'owner');

        $this->assertSame(200, $res['status'],
            'Kräver PDO::MYSQL_ATTR_FOUND_ROWS i Db::connect().');
    }

    public function testUnknownIdGives404AndSomeoneElsesGives403(): void
    {
        $this->seedTwoUsers();

        $missing = $this->request('PUT', '/api/vacations/finns-inte',
            ['start' => '2026-06-16', 'end' => '2026-06-20'], 'other');
        $this->assertSame(404, $missing['status']);

        $forbidden = $this->request('PUT', '/api/vacations/vac1',
            ['start' => '2026-06-16', 'end' => '2026-06-20'], 'other');
        $this->assertSame(403, $forbidden['status']);
    }

    public function testReversedRangeIsRejected(): void
    {
        $this->seedTwoUsers();

        $res = $this->request('POST', '/api/vacations',
            ['start' => '2026-07-05', 'end' => '2026-07-01'], 'owner');

        $this->assertSame(422, $res['status']);
    }

    /**
     * strtotime('2026-02-30') normaliserar till 2 mars och returnerar inte
     * false, så formatkontrollen ensam släpper igenom ett datum som MariaDB
     * (strikt läge) sedan avvisar med ett fel — ett 500 i stället för 422.
     */
    public function testImpossibleCalendarDateIsRejectedWith422(): void
    {
        $this->seedTwoUsers();

        $create = $this->request('POST', '/api/vacations',
            ['start' => '2026-02-30', 'end' => '2026-03-05'], 'owner');
        $this->assertSame(422, $create['status']);

        $update = $this->request('PUT', '/api/vacations/vac1',
            ['start' => '2026-06-16', 'end' => '2026-06-31'], 'owner');
        $this->assertSame(422, $update['status']);
    }

    public function testAdminCreatingForUnknownPersonReturns422(): void
    {
        $this->seedTwoUsers();

        $res = $this->request('POST', '/api/vacations',
            ['personId' => 'finns-inte', 'start' => '2026-07-01', 'end' => '2026-07-05'], 'admin');

        $this->assertSame(422, $res['status']);
    }

    public function testNonStringPersonIdReturns422(): void
    {
        $this->seedTwoUsers();

        $res = $this->request('POST', '/api/vacations',
            ['personId' => ['other'], 'start' => '2026-07-01', 'end' => '2026-07-05'], 'admin');

        $this->assertSame(422, $res['status']);
    }
}
