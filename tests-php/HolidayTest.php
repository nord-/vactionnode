<?php
namespace Tests;

use App\Handlers\HolidayHandler;
use App\Http;
use App\HttpException;
use App\TransportException;
use PHPUnit\Framework\Attributes\DataProvider;

final class HolidayTest extends ApiTestCase
{
    private function seedAdmin(): void
    {
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash, is_admin)
                          VALUES ('h-admin', 'Cia', 'hc@example.com', 'x', 1)");
    }

    private function seedHolidays(): void
    {
        $this->pdo->exec("INSERT INTO holidays (id, date, name) VALUES
            ('h2', '2027-12-24', 'Julafton'),
            ('h1', '2027-01-01', 'Nyårsdagen'),
            ('h3', '2028-01-01', 'Nyårsdagen')");
    }

    private function exists(string $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM holidays WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Importen tar en injicerad HTTP-callable, vilket routern aldrig gör.
     * Hjälparen kör handlern direkt och mappar HttpException som
     * Router::dispatch gör.
     */
    private function import(?callable $http, array $body): array
    {
        $_SESSION = ['user_id' => 'h-admin'];
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        Http::resetTestState();
        Http::setTestBody(json_encode($body));

        ob_start();
        try {
            (new HolidayHandler($this->pdo, $http))->import(['id' => 'h-admin', 'isAdmin' => true]);
        } catch (HttpException $e) {
            Http::error($e->code, $e->getMessage(), $e->status);
        }
        $out = ob_get_clean();

        return ['status' => Http::lastStatus(), 'json' => json_decode($out ?: '{}', true) ?? []];
    }

    public function testIndexReturnsYearSortedByDate(): void
    {
        $this->seedAdmin();
        $this->seedHolidays();

        $res = $this->request('GET', '/api/holidays?year=2027', null, 'h-admin');

        $this->assertSame(200, $res['status']);
        $this->assertSame(
            [['id' => 'h1', 'date' => '2027-01-01', 'name' => 'Nyårsdagen'],
             ['id' => 'h2', 'date' => '2027-12-24', 'name' => 'Julafton']],
            $res['json']
        );
    }

    public static function badYears(): array
    {
        return ['missing' => [''], 'text' => ['?year=abc'], 'low' => ['?year=1999'],
                'high' => ['?year=2101'], 'array' => ['?year[]=2027']];
    }

    #[DataProvider('badYears')]
    public function testIndexRejectsBadYear(string $query): void
    {
        $this->seedAdmin();

        $res = $this->request('GET', '/api/holidays' . $query, null, 'h-admin');

        $this->assertSame(422, $res['status']);
        $this->assertSame('validation_failed', $res['json']['error']['code']);
    }

    public function testCreateReturns201AndTrimsName(): void
    {
        $this->seedAdmin();

        $res = $this->request('POST', '/api/holidays',
            ['date' => '2027-12-31', 'name' => '  Nyårsafton '], 'h-admin');

        $this->assertSame(201, $res['status']);
        $this->assertSame('2027-12-31', $res['json']['date']);
        $this->assertSame('Nyårsafton', $res['json']['name']);
        $this->assertTrue($this->exists($res['json']['id']));
    }

    public static function badCreates(): array
    {
        return [
            'empty name'    => [['date' => '2027-12-31', 'name' => '  ']],
            'long name'     => [['date' => '2027-12-31', 'name' => str_repeat('a', 101)]],
            'missing date'  => [['name' => 'X']],
            'impossible'    => [['date' => '2027-02-30', 'name' => 'X']],
        ];
    }

    #[DataProvider('badCreates')]
    public function testCreateRejectsInvalidInput(array $body): void
    {
        $this->seedAdmin();

        $res = $this->request('POST', '/api/holidays', $body, 'h-admin');

        $this->assertSame(422, $res['status']);
        $this->assertSame('validation_failed', $res['json']['error']['code']);
    }

    public function testCreateAcceptsHundredCharacterName(): void
    {
        $this->seedAdmin();

        $res = $this->request('POST', '/api/holidays',
            ['date' => '2027-12-31', 'name' => str_repeat('å', 100)], 'h-admin');

        $this->assertSame(201, $res['status']);
    }

    public function testCreateRejectsDuplicateDate(): void
    {
        $this->seedAdmin();
        $this->seedHolidays();

        $res = $this->request('POST', '/api/holidays',
            ['date' => '2027-01-01', 'name' => 'Igen'], 'h-admin');

        $this->assertSame(422, $res['status']);
        $this->assertSame('Det datumet har redan en helgdag.', $res['json']['error']['message']);
    }

    public function testUpdateRenamesAndKeepsDate(): void
    {
        $this->seedAdmin();
        $this->seedHolidays();

        $res = $this->request('PUT', '/api/holidays/h2', ['name' => 'Julafton (stängt)'], 'h-admin');

        $this->assertSame(200, $res['status']);
        $this->assertSame(['id' => 'h2', 'date' => '2027-12-24', 'name' => 'Julafton (stängt)'], $res['json']);
    }

    public function testUpdateIgnoresDateInBody(): void
    {
        $this->seedAdmin();
        $this->seedHolidays();

        $res = $this->request('PUT', '/api/holidays/h2', ['name' => 'Annat', 'date' => '2027-12-25'], 'h-admin');

        $this->assertSame(200, $res['status']);
        $this->assertSame(['id' => 'h2', 'date' => '2027-12-24', 'name' => 'Annat'], $res['json']);
        $stmt = $this->pdo->query("SELECT date FROM holidays WHERE id = 'h2'");
        $this->assertSame('2027-12-24', $stmt->fetchColumn());
    }

    public function testUpdateRejectsEmptyName(): void
    {
        $this->seedAdmin();
        $this->seedHolidays();

        $res = $this->request('PUT', '/api/holidays/h2', ['name' => ' '], 'h-admin');

        $this->assertSame(422, $res['status']);
    }

    public function testUpdateUnknownIdIs404(): void
    {
        $this->seedAdmin();

        $res = $this->request('PUT', '/api/holidays/nope', ['name' => 'X'], 'h-admin');

        $this->assertSame(404, $res['status']);
        $this->assertSame('not_found', $res['json']['error']['code']);
    }

    public function testUpdateWithSameNameIsNotNotFound(): void
    {
        $this->seedAdmin();
        $this->seedHolidays();

        $res = $this->request('PUT', '/api/holidays/h2', ['name' => 'Julafton'], 'h-admin');

        $this->assertSame(200, $res['status']);
    }

    public function testDestroyRemovesRow(): void
    {
        $this->seedAdmin();
        $this->seedHolidays();

        $res = $this->request('DELETE', '/api/holidays/h2', null, 'h-admin');

        $this->assertSame(200, $res['status']);
        $this->assertSame(['ok' => true], $res['json']);
        $this->assertFalse($this->exists('h2'));
    }

    public function testDestroyUnknownIdIs404(): void
    {
        $this->seedAdmin();

        $res = $this->request('DELETE', '/api/holidays/nope', null, 'h-admin');

        $this->assertSame(404, $res['status']);
    }

    public function testImportRecordedFixture(): void
    {
        $this->seedAdmin();
        $fixture = json_decode(file_get_contents(__DIR__ . '/fixtures/nager-se-2027.json'), true);

        $res = $this->import(fn (string $url): array => $fixture, ['year' => 2027]);

        $this->assertSame(200, $res['status']);
        $this->assertSame(['imported' => 16, 'skipped' => 0, 'untranslated' => []], $res['json']);
    }

    public function testImportMapsTransportFailureTo502(): void
    {
        $this->seedAdmin();

        $res = $this->import(function (): array {
            throw new TransportException('nere');
        }, ['year' => 2027]);

        $this->assertSame(502, $res['status']);
        $this->assertSame('upstream_failed', $res['json']['error']['code']);
        $this->assertSame('Kunde inte hämta helgdagar just nu.', $res['json']['error']['message']);
    }

    public function testImportRejectsBadYearWithoutCallingUpstream(): void
    {
        $this->seedAdmin();
        $called = false;

        $res = $this->import(function () use (&$called): array {
            $called = true;

            return [];
        }, ['year' => 1999]);

        $this->assertSame(422, $res['status']);
        $this->assertFalse($called);
    }
}
