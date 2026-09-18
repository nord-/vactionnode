<?php
namespace App\Handlers;

use App\Http;
use App\HttpException;
use App\Uuid;
use PDO;

final class GroupHandler
{
    public function __construct(private PDO $pdo) {}

    /**
     * Backstop mot kapplöpningar: före-kontrollen (unikt namn) täcker det
     * vanliga sekventiella fallet, men två samtidiga requests kan båda
     * passera kontrollen och krocka i databasen. SQLSTATE 23000 är en
     * integritetsvillkorskränkning; för teams finns bara UNIQUE-indexet på
     * name (ingen FK), så varje 23000 här är en namnkrock. Andra PDO-fel är
     * inte konstaterade orsaker och kastas vidare oförändrade — front
     * controllern gör dem till 500, vilket är rätt för fel vi inte kan
     * förklara för användaren.
     */
    private function throwForIntegrityViolation(\PDOException $e): never
    {
        if ($e->getCode() !== '23000') {
            throw $e;
        }

        throw new HttpException('validation_failed', 'Gruppnamnet används redan.', 422);
    }

    public function create(?array $user, ?string $id = null): void
    {
        $name = trim((string) (Http::body()['name'] ?? ''));
        if ($name === '') {
            Http::error('validation_failed', 'Gruppen måste ha ett namn.', 422);
            return;
        }
        if ($this->nameTaken($name, null)) {
            Http::error('validation_failed', 'Gruppnamnet används redan.', 422);
            return;
        }

        $newId = Uuid::v4();
        $stmt  = $this->pdo->prepare('INSERT INTO teams (id, name) VALUES (?, ?)');
        try {
            $stmt->execute([$newId, $name]);
        } catch (\PDOException $e) {
            $this->throwForIntegrityViolation($e);
        }

        Http::json(['id' => $newId, 'name' => $name], 201);
    }

    public function update(?array $user, ?string $id = null): void
    {
        $name = trim((string) (Http::body()['name'] ?? ''));
        if ($name === '') {
            Http::error('validation_failed', 'Gruppen måste ha ett namn.', 422);
            return;
        }
        if ($this->nameTaken($name, $id)) {
            Http::error('validation_failed', 'Gruppnamnet används redan.', 422);
            return;
        }

        $stmt = $this->pdo->prepare('UPDATE teams SET name = ? WHERE id = ?');
        try {
            $stmt->execute([$name, $id]);
        } catch (\PDOException $e) {
            $this->throwForIntegrityViolation($e);
        }

        if ($stmt->rowCount() === 0) {
            Http::error('not_found', 'Gruppen finns inte.', 404);
            return;
        }

        Http::json(['id' => $id, 'name' => $name]);
    }

    public function destroy(?array $user, ?string $id = null): void
    {
        // Medlemmarna blir ogrupperade via ON DELETE SET NULL — ingen
        // extra skrivning behövs och ingen person raderas.
        $stmt = $this->pdo->prepare('DELETE FROM teams WHERE id = ?');
        $stmt->execute([$id]);

        Http::json(['ok' => true]);
    }

    private function nameTaken(string $name, ?string $exceptId): bool
    {
        $sql    = 'SELECT 1 FROM teams WHERE name = ?';
        $params = [$name];
        if ($exceptId !== null) {
            $sql     .= ' AND id <> ?';
            $params[] = $exceptId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn() !== false;
    }
}
