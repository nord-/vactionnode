<?php
namespace App\Holidays;

/** Nager.Dates v4 saknar svenska namn, så de engelska översätts med en fast tabell. */
final class SwedishNames
{
    private const NAMES = [
        "New Year's Day"             => 'Nyårsdagen',
        'Epiphany'                   => 'Trettondedag jul',
        'Good Friday'                => 'Långfredagen',
        'Easter Sunday'              => 'Påskdagen',
        'Easter Monday'              => 'Annandag påsk',
        "International Workers' Day" => 'Första maj',
        'Ascension Day'              => 'Kristi himmelsfärdsdag',
        'Pentecost'                  => 'Pingstdagen',
        'National Day of Sweden'     => 'Sveriges nationaldag',
        'Midsummer Eve'              => 'Midsommarafton',
        'Midsummer Day'              => 'Midsommardagen',
        "All Saints' Day"            => 'Alla helgons dag',
        'Christmas Eve'              => 'Julafton',
        'Christmas Day'              => 'Juldagen',
        "St. Stephen's Day"          => 'Annandag jul',
        "New Year's Eve"             => 'Nyårsafton',
    ];

    /** Svenskt namn, eller null för ett namn som inte finns i tabellen. */
    public static function translate(string $english): ?string
    {
        return self::NAMES[$english] ?? null;
    }
}
