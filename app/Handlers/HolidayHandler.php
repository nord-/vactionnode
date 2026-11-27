<?php
namespace App\Handlers;

use App\Dates;
use App\Db;
use App\Holidays\HolidayImport;
use App\Holidays\Holidays;
use App\Holidays\UpstreamException;
use App\Http;
use App\HttpException;
use App\Transport;
use App\Uuid;
use PDO;

final class HolidayHandler
{
    private const DATE_TAKEN = 'Det datumet har redan en helgdag.';
    private const NOT_FOUND  = 'Helgdagen finns inte.';

    /** @var callable */
    private $http;

    public function __construct(private PDO $pdo, ?callable $http = null)
    {
        $this->http = $http ?? [Transport::class, 'request'];
    }

    public function index(?array $user, ?string $id = null): void
    {
        $year = $this->year($_GET['year'] ?? null);

        Http::json(Holidays::between($this->pdo, "$year-01-01", "$year-12-31"));
    }

    public function create(?array $user, ?string $id = null): void
    {
        $body = Http::body();
        $date = $body['date'] ?? null;
        if (!Dates::isValid($date)) {
            throw new HttpException('validation_failed', 'Ogiltigt datum.', 422);
        }
        $name = $this->name($body['name'] ?? null);

        if ($this->dateTaken($date)) {
            throw new HttpException('validation_failed', self::DATE_TAKEN, 422);
        }

        $newId = Uuid::v4();
        $stmt  = $this->pdo->prepare('INSERT INTO holidays (id, date, name) VALUES (?, ?, ?)');
        try {
            $stmt->execute([$newId, $date, $name]);
        } catch (\PDOException $e) {
            $this->throwForIntegrityViolation($e);
        }

        Http::json(['id' => $newId, 'date' => $date, 'name' => $name], 201);
    }

    public function update(?array $user, ?string $id = null): void
    {
        $name = $this->name(Http::body()['name'] ?? null);

        // rowCount() är 0 även när namnet är oförändrat, så existensen
        // kontrolleras med en egen läsning.
        $stmt = $this->pdo->prepare('SELECT date FROM holidays WHERE id = ?');
        $stmt->execute([$id]);
        $date = $stmt->fetchColumn();
        if ($date === false) {
            throw new HttpException('not_found', self::NOT_FOUND, 404);
        }

        $this->pdo->prepare('UPDATE holidays SET name = ? WHERE id = ?')->execute([$name, $id]);

        Http::json(['id' => $id, 'date' => $date, 'name' => $name]);
    }

    public function destroy(?array $user, ?string $id = null): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM holidays WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            throw new HttpException('not_found', self::NOT_FOUND, 404);
        }

        Http::json(['ok' => true]);
    }

    public function import(?array $user, ?string $id = null): void
    {
        $year = $this->year(Http::body()['year'] ?? null);

        try {
            $result = (new HolidayImport($this->pdo, $this->http))->run($year);
        } catch (UpstreamException) {
            throw new HttpException('upstream_failed', 'Kunde inte hämta helgdagar just nu.', 502);
        }

        Http::json($result);
    }

    /**
     * Gemensam årsvalidering för index (query-sträng) och import (body).
     * filter_var avvisar arrayer, text och decimaltal i ett steg.
     */
    private function year(mixed $value): int
    {
        $year = filter_var($value, FILTER_VALIDATE_INT);
        if ($year === false || $year < 2000 || $year > 2100) {
            throw new HttpException('validation_failed', 'Ogiltigt år.', 422);
        }

        return $year;
    }

    private function name(mixed $value): string
    {
        $name = is_string($value) ? trim($value) : '';
        if ($name === '' || mb_strlen($name) > Holidays::NAME_MAX) {
            throw new HttpException(
                'validation_failed',
                sprintf('Helgdagen måste ha ett namn på högst %d tecken.', Holidays::NAME_MAX),
                422
            );
        }

        return $name;
    }

    private function dateTaken(string $date): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM holidays WHERE date = ?');
        $stmt->execute([$date]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Backstop mot kapplöpningar, som i GroupHandler: förkontrollen täcker
     * det sekventiella fallet, men två samtidiga requests kan båda passera
     * den. holidays har bara UNIQUE-indexet på date, så varje 23000 är en
     * datumkrock. Andra PDO-fel kastas vidare och blir 500.
     */
    private function throwForIntegrityViolation(\PDOException $e): never
    {
        if (!Db::isIntegrityViolation($e)) {
            throw $e;
        }

        throw new HttpException('validation_failed', self::DATE_TAKEN, 422);
    }
}
