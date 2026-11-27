<?php
namespace App\Holidays;

use PDO;

final class Holidays
{
    public const NAME_MAX = 100;

    /** @return array<int, array{id: string, date: string, name: string}> */
    public static function between(PDO $pdo, string $from, string $to): array
    {
        $stmt = $pdo->prepare(
            'SELECT id, date, name FROM holidays WHERE date BETWEEN ? AND ? ORDER BY date'
        );
        $stmt->execute([$from, $to]);

        return $stmt->fetchAll();
    }
}
