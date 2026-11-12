<?php
namespace App;

final class Dates
{
    /**
     * Strikt YYYY-MM-DD som också finns i kalendern. strtotime() duger inte:
     * '2026-02-30' normaliseras till 2 mars i stället för att ge false, och
     * MariaDB (strikt läge) avvisar sedan strängen med ett fel -> 500.
     * Tur-och-retur-jämförelsen släpper bara igenom datum som är oförändrade.
     */
    public static function isValid(mixed $value): bool
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $dt !== false && $dt->format('Y-m-d') === $value;
    }
}
