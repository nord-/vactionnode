<?php
namespace Tests;

use App\Holidays\SwedishNames;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SwedishNamesTest extends TestCase
{
    public static function pairs(): array
    {
        return [
            ["New Year's Day", 'Nyårsdagen'],
            ['Epiphany', 'Trettondedag jul'],
            ['Good Friday', 'Långfredagen'],
            ['Easter Sunday', 'Påskdagen'],
            ['Easter Monday', 'Annandag påsk'],
            ["International Workers' Day", 'Första maj'],
            ['Ascension Day', 'Kristi himmelsfärdsdag'],
            ['Pentecost', 'Pingstdagen'],
            ['National Day of Sweden', 'Sveriges nationaldag'],
            ['Midsummer Eve', 'Midsommarafton'],
            ['Midsummer Day', 'Midsommardagen'],
            ["All Saints' Day", 'Alla helgons dag'],
            ['Christmas Eve', 'Julafton'],
            ['Christmas Day', 'Juldagen'],
            ["St. Stephen's Day", 'Annandag jul'],
            ["New Year's Eve", 'Nyårsafton'],
        ];
    }

    #[DataProvider('pairs')]
    public function testTranslatesKnownName(string $english, string $swedish): void
    {
        $this->assertSame($swedish, SwedishNames::translate($english));
    }

    public function testUnknownNameGivesNull(): void
    {
        $this->assertNull(SwedishNames::translate('Boxing Day'));
    }
}
