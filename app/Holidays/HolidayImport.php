<?php
namespace App\Holidays;

use App\Dates;
use App\Db;
use App\TransportException;
use App\Uuid;
use PDO;
use PDOException;
use Throwable;

final class HolidayImport
{
    private const URL = 'https://nagerholidays.com/api/v4/Holidays/SE/%d';

    /** @var callable(string): array */
    private $http;

    public function __construct(private PDO $pdo, callable $http)
    {
        $this->http = $http;
    }

    /** @return array{imported: int, skipped: int, untranslated: string[]} */
    public function run(int $year): array
    {
        try {
            $entries = ($this->http)(sprintf(self::URL, $year));
        } catch (TransportException $e) {
            throw new UpstreamException($e->getMessage(), 0, $e);
        }

        // Hela svaret valideras (även poster som sedan filtreras bort) innan något sparas.
        $this->validate($entries);

        $public = array_filter($entries, fn (array $e) => in_array('Public', $e['holidayTypes'], true));

        // DbTestCase har redan en öppen transaktion; då får vi inte starta en egen.
        $ownTransaction = !$this->pdo->inTransaction();
        if ($ownTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $result = $this->insertAll($public);
            if ($ownTransaction) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($ownTransaction) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $result;
    }

    private function validate(mixed $entries): void
    {
        if (!is_array($entries) || !array_is_list($entries)) {
            throw new UpstreamException('Svaret är inte en lista.');
        }

        foreach ($entries as $e) {
            if (
                !is_array($e)
                || !Dates::isValid($e['date'] ?? null)
                || !is_string($e['name'] ?? null) || trim($e['name']) === ''
                || !is_array($e['holidayTypes'] ?? null) || !array_is_list($e['holidayTypes'])
            ) {
                throw new UpstreamException('Svaret innehåller en felaktig post.');
            }
        }
    }

    private function insertAll(array $entries): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO holidays (id, date, name) VALUES (?, ?, ?)');
        $imported = 0;
        $skipped = 0;
        $untranslated = [];

        foreach ($entries as $e) {
            // Valideringen kontrollerar det trimmade namnet, så det är det som
            // översätts, rapporteras och sparas.
            $original = trim($e['name']);
            $swedish = SwedishNames::translate($original);
            // Kapas vid sparandet så att kolumnen aldrig avvisar en rad.
            $name = mb_substr($swedish ?? $original, 0, Holidays::NAME_MAX);

            try {
                $stmt->execute([Uuid::v4(), $e['date'], $name]);
            } catch (PDOException $ex) {
                // Datumet har redan en helgdag: lämna den orörd.
                if (!Db::isIntegrityViolation($ex)) {
                    throw $ex;
                }
                $skipped++;
                continue;
            }

            $imported++;
            if ($swedish === null) {
                $untranslated[] = $original;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'untranslated' => $untranslated];
    }
}
