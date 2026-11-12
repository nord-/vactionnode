<?php
namespace Tests;

use PDOException;

final class SchemaTest extends DbTestCase
{
    public function testCheckConstraintRejectsReversedRange(): void
    {
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash)
                          VALUES ('u1', 'Anna', 'anna@example.com', 'x')");

        $this->expectException(PDOException::class);
        $this->pdo->exec("INSERT INTO vacations (id, user_id, start_date, end_date)
                          VALUES ('v1', 'u1', '2026-06-20', '2026-06-16')");
    }

    public function testDeletingUserCascadesToVacations(): void
    {
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash)
                          VALUES ('u2', 'Bo', 'bo@example.com', 'x')");
        $this->pdo->exec("INSERT INTO vacations (id, user_id, start_date, end_date)
                          VALUES ('v2', 'u2', '2026-06-16', '2026-06-20')");

        $this->pdo->exec("DELETE FROM users WHERE id = 'u2'");

        $count = $this->pdo->query("SELECT COUNT(*) FROM vacations WHERE id = 'v2'")
                           ->fetchColumn();
        $this->assertSame(0, (int) $count);
    }

    public function testDeletingTeamUngroupsMembers(): void
    {
        $this->pdo->exec("INSERT INTO teams (id, name) VALUES ('t1', 'Team A')");
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash, team_id)
                          VALUES ('u3', 'Cia', 'cia@example.com', 'x', 't1')");

        $this->pdo->exec("DELETE FROM teams WHERE id = 't1'");

        $teamId = $this->pdo->query("SELECT team_id FROM users WHERE id = 'u3'")
                            ->fetchColumn();
        $this->assertNull($teamId);
    }
}
