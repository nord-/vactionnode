<?php
namespace Tests;

use App\Db;
use App\Persons;
use PDOException;
use PHPUnit\Framework\TestCase;

final class LastAdminConcurrencyTest extends TestCase
{
    /**
     * Metod, inte konstant: en klasskonstant kan inte anropa getenv().
     * Inuti php-containern heter databasen db:3306, inte 127.0.0.1:3307.
     */
    private static function dsn(): array
    {
        return [
            'host' => getenv('DB_HOST') ?: 'db',
            'port' => (int) (getenv('DB_PORT') ?: 3306),
            'name' => 'vacation_test',
            'user' => 'root',
            'pass' => 'root',
        ];
    }

    public function testConcurrentDemotionsCannotBothSucceed(): void
    {
        $setup = Db::connect(self::dsn());
        $setup->exec("DELETE FROM users WHERE id IN ('conc1', 'conc2')");
        $setup->exec("INSERT INTO users (id, name, email, password_hash, is_admin) VALUES
            ('conc1', 'A', 'conc1@example.com', 'x', 1),
            ('conc2', 'B', 'conc2@example.com', 'x', 1)");

        $a = Db::connect(self::dsn());
        $b = Db::connect(self::dsn());
        $b->exec('SET SESSION innodb_lock_wait_timeout = 2');

        try {
            $a->beginTransaction();
            $b->beginTransaction();

            // A tar låset och degraderar conc1. Transaktionen hålls öppen, så
            // låset på adminraderna släpps inte.
            $this->assertFalse(Persons::wouldLeaveNoAdmin($a, 'conc1'));
            $a->exec("UPDATE users SET is_admin = 0 WHERE id = 'conc1'");

            // B måste nu blockera på samma rader och falla på lock wait timeout.
            // Utan FOR UPDATE läser B i stället sitt eget snapshot, ser två
            // administratörer, returnerar false direkt — och då blir det här
            // testet rött, vilket är hela poängen med det.
            // errorInfo[1] === 1205 är MariaDBs "Lock wait timeout exceeded".
            // Ett bredare catch(PDOException) hade satt $blocked = true för
            // vilket PDO-fel som helst — fel lösenord, saknad tabell, en
            // SQL-typo — och testet hade blivit grönt av fel anledning.
            $blocked = false;
            try {
                Persons::wouldLeaveNoAdmin($b, 'conc2');
            } catch (PDOException $e) {
                $blocked = ((int) ($e->errorInfo[1] ?? 0)) === 1205;
            }

            $this->assertTrue(
                $blocked,
                'SELECT ... FOR UPDATE måste blockera den andra transaktionen. '
                . 'Blockerade den inte kan två samtidiga degraderingar lämna '
                . 'systemet utan administratör.'
            );
        } finally {
            $a->rollBack();
            $b->rollBack();
            $setup->exec("DELETE FROM users WHERE id IN ('conc1', 'conc2')");
        }
    }
}
