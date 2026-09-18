<?php
namespace App\Handlers;

use App\Http;
use App\HttpException;
use App\Persons;
use PDO;

final class PersonHandler
{
    public function __construct(private PDO $pdo) {}

    /**
     * MariaDB har inga nästlade transaktioner, och DbTestCase håller redan en
     * öppen. Äger vi inte transaktionen rör vi varken begin, commit eller
     * rollBack — en rollBack här hade rullat tillbaka testets egen uppsättning
     * och fått orelaterade tester att falla.
     */
    private function transactional(callable $fn): mixed
    {
        $owned = !$this->pdo->inTransaction();
        if ($owned) {
            $this->pdo->beginTransaction();
        }

        try {
            $result = $fn();
            if ($owned) {
                $this->pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if ($owned) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Backstop mot kapplöpningar: före-kontrollerna (unik e-post, giltig
     * groupId) täcker det vanliga sekventiella fallet, men två samtidiga
     * requests kan båda passera kontrollen och krocka i databasen. SQLSTATE
     * 23000 är en integritetsvillkorskränkning; drivrutinskoden skiljer
     * dubblett-e-post (1062) från en groupId som inte finns (1452, FK mot
     * teams). Andra PDO-fel är inte konstaterade orsaker och kastas vidare
     * oförändrade — front controllern gör dem till 500, vilket är rätt för
     * fel vi inte kan förklara för användaren.
     */
    private function throwForIntegrityViolation(\PDOException $e): never
    {
        if ($e->getCode() !== '23000') {
            throw $e;
        }

        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        throw match ($driverCode) {
            1062    => new HttpException('validation_failed', 'E-postadressen används redan.', 422),
            1452    => new HttpException('validation_failed', 'Gruppen finns inte.', 422),
            default => new HttpException('validation_failed', 'Ogiltiga uppgifter.', 422),
        };
    }

    public function create(?array $user, ?string $id = null): void
    {
        $body     = Http::body();
        $name     = trim((string) ($body['name'] ?? ''));
        $email    = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            Http::error('validation_failed',
                'Namn, giltig e-post och lösenord på minst 8 tecken krävs.', 422);
            return;
        }

        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([strtolower($email)]);
        if ($stmt->fetchColumn() !== false) {
            Http::error('validation_failed', 'E-postadressen används redan.', 422);
            return;
        }

        try {
            $person = Persons::create($this->pdo, $name, $email, $password,
                $body['groupId'] ?? null, (bool) ($body['isAdmin'] ?? false));
        } catch (\PDOException $e) {
            $this->throwForIntegrityViolation($e);
        }

        Http::json($person, 201);
    }

    public function update(?array $user, ?string $id = null): void
    {
        $body = Http::body();

        // E-post är inloggningsidentiteten och nyckeln en framtida
        // Entra ID-koppling kommer att länka konton på, så samma krav som
        // create() gäller — innan någon SQL körs.
        if (array_key_exists('email', $body) && !filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
            throw new HttpException('validation_failed', 'Ogiltig e-postadress.', 422);
        }

        $this->transactional(function () use ($body, $id): void {
        // array_key_exists, inte isset: isset() är false för null och skulle
        // tyst svälja varje försök att göra en person ogrupperad.
        $demoting = array_key_exists('isAdmin', $body) && !$body['isAdmin'];
        if ($demoting && Persons::wouldLeaveNoAdmin($this->pdo, $id)) {
            throw new HttpException('validation_failed',
                'Det måste finnas minst en administratör.', 422);
        }

        $columns = [
            'name'     => 'name',
            'email'    => 'email',
            'groupId'  => 'team_id',
            'isAdmin'  => 'is_admin',
        ];

        $sets   = [];
        $params = [];
        foreach ($columns as $field => $column) {
            if (!array_key_exists($field, $body)) {
                continue;
            }
            $sets[]   = "$column = ?";
            $params[] = match ($field) {
                'email'   => strtolower(trim((string) $body['email'])),
                'isAdmin' => (int) $body['isAdmin'],
                default   => $body[$field],
            };
        }

        if (array_key_exists('password', $body) && (string) $body['password'] !== '') {
            $sets[]   = 'password_hash = ?';
            $params[] = password_hash((string) $body['password'], PASSWORD_DEFAULT);
        }

        if ($sets !== []) {
            $params[] = $id;
            $stmt = $this->pdo->prepare(
                'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?'
            );
            try {
                $stmt->execute($params);
            } catch (\PDOException $e) {
                $this->throwForIntegrityViolation($e);
            }
        }
        });

        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, team_id AS groupId, is_admin AS isAdmin
             FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if ($row === false) {
            throw new HttpException('not_found', 'Personen finns inte.', 404);
        }

        $row['isAdmin'] = (bool) $row['isAdmin'];
        Http::json($row);
    }

    public function destroy(?array $user, ?string $id = null): void
    {
        $this->transactional(function () use ($id): void {
            if (Persons::wouldLeaveNoAdmin($this->pdo, $id)) {
                throw new HttpException('validation_failed',
                    'Det måste finnas minst en administratör.', 422);
            }

            $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$id]);
        });

        Http::json(['ok' => true]);
    }
}
