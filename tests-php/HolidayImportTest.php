<?php
namespace Tests;

use App\Holidays\HolidayImport;
use App\Holidays\UpstreamException;
use App\TransportException;
use PHPUnit\Framework\Attributes\DataProvider;

final class HolidayImportTest extends DbTestCase
{
    private ?string $calledUrl = null;

    private function fixture(): array
    {
        return json_decode(
            file_get_contents(__DIR__ . '/fixtures/nager-se-2027.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    private function import(?array $response = null): HolidayImport
    {
        $response ??= $this->fixture();

        return new HolidayImport($this->pdo, function (string $url) use ($response): array {
            $this->calledUrl = $url;

            return $response;
        });
    }

    private function entry(string $date, string $name, array $types = ['Public']): array
    {
        return ['date' => $date, 'name' => $name, 'countryCode' => 'SE',
                'nationalHoliday' => true, 'subdivisionCodes' => null, 'holidayTypes' => $types];
    }

    private function rowCount(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM holidays')->fetchColumn();
    }

    private function nameOn(string $date): ?string
    {
        $stmt = $this->pdo->prepare('SELECT name FROM holidays WHERE date = ?');
        $stmt->execute([$date]);

        return $stmt->fetchColumn() ?: null;
    }

    public function testTrimsNameBeforeTranslatingAndStoring(): void
    {
        $result = $this->import([$this->entry('2027-12-24', 'Christmas Eve ')])->run(2027);

        $this->assertSame(['imported' => 1, 'skipped' => 0, 'untranslated' => []], $result);
        $this->assertSame('Julafton', $this->nameOn('2027-12-24'));
    }

    public function testImportsRecordedFixture(): void
    {
        $result = $this->import()->run(2027);

        $this->assertSame(['imported' => 16, 'skipped' => 0, 'untranslated' => []], $result);
        $this->assertSame('https://nagerholidays.com/api/v4/Holidays/SE/2027', $this->calledUrl);
        $this->assertSame('Midsommarafton', $this->nameOn('2027-06-25'));
    }

    public function testSecondImportSkipsEverything(): void
    {
        $this->import()->run(2027);
        $result = $this->import()->run(2027);

        $this->assertSame(['imported' => 0, 'skipped' => 16, 'untranslated' => []], $result);
        $this->assertSame(16, $this->rowCount());
    }

    public function testExistingNameIsNotOverwritten(): void
    {
        $this->pdo->exec("INSERT INTO holidays (id, date, name)
                          VALUES ('h1', '2027-12-24', 'Julafton (stängt)')");

        $result = $this->import()->run(2027);

        $this->assertSame(15, $result['imported']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame('Julafton (stängt)', $this->nameOn('2027-12-24'));
    }

    public function testUnknownNameSavedInEnglish(): void
    {
        $response = [$this->entry('2027-03-01', 'Foo Day')];
        $result = $this->import($response)->run(2027);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(['Foo Day'], $result['untranslated']);
        $this->assertSame('Foo Day', $this->nameOn('2027-03-01'));

        $again = $this->import($response)->run(2027);
        $this->assertSame(0, $again['imported']);
        $this->assertSame([], $again['untranslated']);
    }

    public function testLongUnknownNameIsTruncatedWhenSaved(): void
    {
        $name = str_repeat('å', 120);
        $result = $this->import([$this->entry('2027-03-01', $name)])->run(2027);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(str_repeat('å', 100), $this->nameOn('2027-03-01'));
    }

    public function testNonPublicIsIgnored(): void
    {
        $result = $this->import([$this->entry('2027-03-01', 'Foo Day', ['Observance'])])->run(2027);

        $this->assertSame(['imported' => 0, 'skipped' => 0, 'untranslated' => []], $result);
        $this->assertSame(0, $this->rowCount());
    }

    public static function malformed(): array
    {
        $ok = ['date' => '2027-03-01', 'name' => 'Foo Day', 'holidayTypes' => ['Public']];

        return [
            'object, not a list'  => [['date' => 'x']],
            'missing name'        => [[$ok, ['date' => '2027-03-02', 'holidayTypes' => ['Public']]]],
            'empty name'          => [[$ok, ['date' => '2027-03-02', 'name' => '', 'holidayTypes' => ['Public']]]],
            'invalid date'        => [[$ok, ['date' => '2027-02-30', 'name' => 'X', 'holidayTypes' => ['Public']]]],
            'missing types'       => [[$ok, ['date' => '2027-03-02', 'name' => 'X']]],
            'types not a list'    => [[$ok, ['date' => '2027-03-02', 'name' => 'X', 'holidayTypes' => 'Public']]],
            'non-public invalid'  => [[$ok, ['date' => 'nope', 'name' => 'X', 'holidayTypes' => ['Observance']]]],
            'entry not an object' => [[$ok, 'text']],
        ];
    }

    #[DataProvider('malformed')]
    public function testMalformedResponseThrowsAndSavesNothing(array $response): void
    {
        try {
            $this->import($response)->run(2027);
            $this->fail('UpstreamException förväntades');
        } catch (UpstreamException) {
            $this->assertSame(0, $this->rowCount());
        }
    }

    public function testTransportFailureThrowsUpstream(): void
    {
        $import = new HolidayImport($this->pdo, function (): array {
            throw new TransportException('nere');
        });

        try {
            $import->run(2027);
            $this->fail('UpstreamException förväntades');
        } catch (UpstreamException) {
            $this->assertSame(0, $this->rowCount());
        }
    }
}
