<?php
namespace App\Handlers;

use App\Dates;
use App\Http;
use App\HttpException;
use App\Uuid;
use PDO;

final class VacationHandler
{
    public function __construct(private PDO $pdo) {}

    public function create(?array $user, ?string $id = null): void
    {
        $body  = Http::body();
        $start = (string) ($body['start'] ?? '');
        $end   = (string) ($body['end'] ?? '');

        if (!$this->validRange($start, $end)) {
            Http::error('validation_failed', 'Slutdatum måste vara samma dag eller senare.', 422);
            return;
        }

        // Endast admin får skriva semester åt någon annan. För övriga kommer
        // personId från sessionen, aldrig från klienten.
        $personId = $user['id'];
        if ($user['isAdmin'] && isset($body['personId'])) {
            // is_string före användning: en array hade annars castats till
            // "Array" och blivit en FK-krock i stället för ett tydligt 422.
            if (!is_string($body['personId'])) {
                throw new HttpException('validation_failed', 'Ogiltigt personId.', 422);
            }
            $personId = $body['personId'];
        }

        $newId = Uuid::v4();
        $stmt  = $this->pdo->prepare(
            'INSERT INTO vacations (id, user_id, start_date, end_date) VALUES (?, ?, ?, ?)'
        );
        try {
            $stmt->execute([$newId, $personId, $start, $end]);
        } catch (\PDOException $e) {
            // 1452 = fk_vac_user: personen finns inte. Andra fel kastas vidare
            // och blir 500 — de är inget vi kan förklara för användaren.
            if ($e->getCode() === '23000' && (int) ($e->errorInfo[1] ?? 0) === 1452) {
                throw new HttpException('validation_failed', 'Personen finns inte.', 422);
            }
            throw $e;
        }

        Http::json(
            ['id' => $newId, 'personId' => $personId, 'start' => $start, 'end' => $end],
            201
        );
    }

    public function update(?array $user, ?string $id = null): void
    {
        $body  = Http::body();
        $start = (string) ($body['start'] ?? '');
        $end   = (string) ($body['end'] ?? '');

        if (!$this->validRange($start, $end)) {
            Http::error('validation_failed', 'Slutdatum måste vara samma dag eller senare.', 422);
            return;
        }

        // Ägarvillkoret upprepas som försvar på djupet; vakten har redan
        // svarat 404 eller 403 om raden saknas eller tillhör någon annan.
        $stmt = $this->pdo->prepare(
            'UPDATE vacations SET start_date = ?, end_date = ?
             WHERE id = ? AND (user_id = ? OR ? = 1)'
        );
        $stmt->execute([$start, $end, $id, $user['id'], (int) $user['isAdmin']]);

        Http::json(['id' => $id, 'start' => $start, 'end' => $end]);
    }

    public function destroy(?array $user, ?string $id = null): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM vacations WHERE id = ? AND (user_id = ? OR ? = 1)'
        );
        $stmt->execute([$id, $user['id'], (int) $user['isAdmin']]);

        Http::json(['ok' => true]);
    }

    private function validRange(string $start, string $end): bool
    {
        return Dates::isValid($start) && Dates::isValid($end) && $end >= $start;
    }
}
