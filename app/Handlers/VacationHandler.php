<?php
namespace App\Handlers;

use App\Http;
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
        $personId = $user['isAdmin'] && isset($body['personId'])
            ? (string) $body['personId']
            : $user['id'];

        $newId = Uuid::v4();
        $stmt  = $this->pdo->prepare(
            'INSERT INTO vacations (id, user_id, start_date, end_date) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$newId, $personId, $start, $end]);

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
        $isDate = static fn (string $d): bool =>
            (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;

        return $isDate($start) && $isDate($end) && $end >= $start;
    }
}
