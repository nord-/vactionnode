<?php
namespace Tests;

final class PersonTest extends ApiTestCase
{
    private function seedAdmin(string $id = 'p-admin'): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (id, name, email, password_hash, is_admin) VALUES (?, ?, ?, ?, 1)'
        );
        $stmt->execute([$id, 'Admin', $id . '@example.com', password_hash('x', PASSWORD_DEFAULT)]);
    }

    public function testCreateStoresHashedPasswordAndLowercasedEmail(): void
    {
        $this->seedAdmin();

        $res = $this->request('POST', '/api/persons', [
            'name' => 'Anna', 'email' => 'Anna@Example.COM',
            'password' => 'hemligt123', 'groupId' => null, 'isAdmin' => false,
        ], 'p-admin');

        $this->assertSame(201, $res['status']);
        $this->assertSame('anna@example.com', $res['json']['email']);
        $this->assertArrayNotHasKey('password', $res['json']);

        $hash = $this->pdo->query("SELECT password_hash FROM users WHERE email = 'anna@example.com'")
                          ->fetchColumn();
        $this->assertNotSame('hemligt123', $hash);
        $this->assertTrue(password_verify('hemligt123', $hash));
    }

    public function testPartialUpdateLeavesOtherFieldsUntouched(): void
    {
        $this->seedAdmin();
        $this->pdo->exec("INSERT INTO teams (id, name) VALUES ('p-team', 'Team A')");
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash, is_admin)
                          VALUES ('p1', 'Anna', 'anna@example.com', 'x', 0)");

        $res = $this->request('PUT', '/api/persons/p1', ['groupId' => 'p-team'], 'p-admin');

        $this->assertSame(200, $res['status']);
        $row = $this->pdo->query("SELECT name, email, is_admin, team_id FROM users WHERE id = 'p1'")
                         ->fetch();
        $this->assertSame('Anna', $row['name']);
        $this->assertSame('anna@example.com', $row['email']);
        $this->assertSame(0, (int) $row['is_admin']);
        $this->assertSame('p-team', $row['team_id']);
    }

    public function testExplicitNullGroupIdUngroupsButOmittedLeavesItAlone(): void
    {
        $this->seedAdmin();
        $this->pdo->exec("INSERT INTO teams (id, name) VALUES ('p-team2', 'Team B')");
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash, team_id)
                          VALUES ('p2', 'Bo', 'bo@example.com', 'x', 'p-team2')");

        // Utelämnad groupId: gruppen ska vara kvar.
        $this->request('PUT', '/api/persons/p2', ['name' => 'Bosse'], 'p-admin');
        $this->assertSame('p-team2',
            $this->pdo->query("SELECT team_id FROM users WHERE id = 'p2'")->fetchColumn());

        // Explicit null: personen ska bli ogrupperad.
        // Detta test failar om implementationen använder isset() i stället
        // för array_key_exists() — isset() är false för null.
        $this->request('PUT', '/api/persons/p2', ['groupId' => null], 'p-admin');
        $this->assertNull(
            $this->pdo->query("SELECT team_id FROM users WHERE id = 'p2'")->fetchColumn());
    }

    public function testLastAdminCannotDemoteSelf(): void
    {
        $this->seedAdmin();

        $res = $this->request('PUT', '/api/persons/p-admin', ['isAdmin' => false], 'p-admin');

        $this->assertSame(422, $res['status']);
        $this->assertSame(1,
            (int) $this->pdo->query("SELECT is_admin FROM users WHERE id = 'p-admin'")->fetchColumn());
    }

    public function testLastAdminCannotDeleteSelf(): void
    {
        $this->seedAdmin();

        $res = $this->request('DELETE', '/api/persons/p-admin', null, 'p-admin');

        $this->assertSame(422, $res['status']);
    }

    public function testDemotionSucceedsWhenAnotherAdminExists(): void
    {
        $this->seedAdmin('p-admin');
        $this->seedAdmin('p-admin2');

        $res = $this->request('PUT', '/api/persons/p-admin2', ['isAdmin' => false], 'p-admin');

        $this->assertSame(200, $res['status']);
    }

    public function testDeletingPersonRemovesTheirVacations(): void
    {
        $this->seedAdmin();
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash)
                          VALUES ('p3', 'Cia', 'cia@example.com', 'x')");
        $this->pdo->exec("INSERT INTO vacations (id, user_id, start_date, end_date)
                          VALUES ('p-vac', 'p3', '2026-06-16', '2026-06-20')");

        $this->request('DELETE', '/api/persons/p3', null, 'p-admin');

        $this->assertSame(0, (int) $this->pdo
            ->query("SELECT COUNT(*) FROM vacations WHERE id = 'p-vac'")->fetchColumn());
    }
}
