<?php
namespace App\Handlers;

use App\Dates;
use App\Http;
use App\HttpException;
use PDO;

final class BootstrapHandler
{
    public function __construct(private PDO $pdo) {}

    public function index(?array $user, ?string $id = null): void
    {
        $from = $this->resolveDate($_GET['from'] ?? null, date('Y-m-01'));
        $to   = $this->resolveDate($_GET['to']   ?? null, date('Y-m-t'));

        $groups = $this->pdo
            ->query('SELECT id, name FROM teams ORDER BY name')
            ->fetchAll();

        $persons = $this->pdo
            ->query('SELECT id, name, email, team_id AS groupId, is_admin AS isAdmin
                     FROM users ORDER BY name')
            ->fetchAll();

        foreach ($persons as &$p) {
            $p['isAdmin'] = (bool) $p['isAdmin'];
        }
        unset($p);

        // Överlapp, inte inneslutning: en semester som börjar före intervallet
        // och slutar inuti det måste synas i kalendern.
        // `start` och `end` backtickas — END är reserverat i MariaDB.
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id AS personId, start_date AS `start`, end_date AS `end`
             FROM vacations
             WHERE start_date <= ? AND end_date >= ?
             ORDER BY start_date'
        );
        $stmt->execute([$to, $from]);

        Http::json([
            'me'        => $user,
            'groups'    => $groups,
            'persons'   => $persons,
            'vacations' => $stmt->fetchAll(),
        ]);
    }

    /**
     * ?from[]=x når PHP som en array, inte en sträng — att skicka den vidare
     * till execute() kastar en PDOException och blir ett obehjälpsamt 500.
     * Avvisa allt som inte är ett YYYY-MM-DD-datum med 422; ett utelämnat
     * värde faller tillbaka på standardintervallet precis som tidigare.
     */
    private function resolveDate(mixed $value, string $default): string
    {
        if ($value === null) {
            return $default;
        }

        if (!Dates::isValid($value)) {
            throw new HttpException('validation_failed', 'Ogiltigt datum.', 422);
        }

        return $value;
    }
}
