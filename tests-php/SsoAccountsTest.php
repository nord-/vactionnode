<?php
namespace Tests;

use App\Sso\SsoAccounts;
use App\Sso\SsoException;

final class SsoAccountsTest extends ApiTestCase
{
    private const OID = '0f0f0f0f-1111-2222-3333-444444444444';

    public function testFirstSignInLinksExistingAccountByEmailAndClearsItsPassword(): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO users (id, name, email, password_hash)
                                     VALUES ('b1', 'Bo', 'bo@example.com', ?)");
        $stmt->execute([password_hash('hemligt123', PASSWORD_DEFAULT)]);

        $this->assertSame('b1', SsoAccounts::resolve($this->pdo, self::OID, 'Bo@Example.com', 'Bo B'));

        // Länken är bestående: nästa inloggning hittas via oid även om
        // e-posten i tokenet har bytts.
        $this->assertSame('b1', SsoAccounts::resolve($this->pdo, self::OID, 'bo.ny@example.com', 'Bo B'));

        // Lösenordet rensades vid länkningen.
        $res = $this->request('POST', '/api/session', ['email' => 'bo@example.com', 'password' => 'hemligt123']);
        $this->assertSame(401, $res['status']);
    }

    /**
     * E-posten kan ha återanvänts för en ny person i tenanten. Att skriva
     * över det gamla oid:t hade gett den nya personen den gamlas konto.
     */
    public function testEmailOwnedByAnotherOidIsRefused(): void
    {
        $this->pdo->exec("INSERT INTO users (id, name, email, sso_id)
                          VALUES ('c1', 'Cia', 'cia@example.com', 'ett-annat-oid')");

        try {
            SsoAccounts::resolve($this->pdo, self::OID, 'cia@example.com', 'Cia C');
            $this->fail('Kontot länkades om till ett nytt oid.');
        } catch (SsoException $e) {
            $this->assertSame('account_conflict', $e->reason);
        }

        $this->assertSame('c1', SsoAccounts::resolve($this->pdo, 'ett-annat-oid', 'cia@example.com', 'Cia C'));
    }

    public function testUnknownPersonIsProvisionedWithoutAdminOrGroup(): void
    {
        $id = SsoAccounts::resolve($this->pdo, self::OID, ' Dag@Example.com ', 'Dag Dagsson');

        $stmt = $this->pdo->prepare('SELECT name, email, sso_id, team_id, is_admin FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $this->assertEquals(
            ['name' => 'Dag Dagsson', 'email' => 'dag@example.com', 'sso_id' => self::OID, 'team_id' => null, 'is_admin' => 0],
            $stmt->fetch()
        );

        $this->assertSame($id, SsoAccounts::resolve($this->pdo, self::OID, 'dag@example.com', 'Dag Dagsson'));

        // Kontot saknar lösenord och går bara att nå via Microsoft.
        $res = $this->request('POST', '/api/session', ['email' => 'dag@example.com', 'password' => '']);
        $this->assertSame(401, $res['status']);
    }

    /** Förloraren i ett lopp mot en samtidig första inloggning får vinnarens konto. */
    public function testConcurrentFirstSignInReusesTheAccountTheWinnerCreated(): void
    {
        $this->pdo->exec("INSERT INTO users (id, name, email, sso_id)
                          VALUES ('w1', 'Eva', 'eva@example.com', '" . self::OID . "')");

        $provision = new \ReflectionMethod(SsoAccounts::class, 'provision');
        $this->assertSame('w1', $provision->invoke(null, $this->pdo, self::OID, 'eva@example.com', 'Eva E'));

        $link = new \ReflectionMethod(SsoAccounts::class, 'link');
        $link->invoke(null, $this->pdo, 'w1', self::OID);
        $this->addToAssertionCount(1);
    }

    public function testKnownOidSignsInEvenWhenEmailHasChanged(): void
    {
        $this->pdo->exec("INSERT INTO users (id, name, email, sso_id)
                          VALUES ('a1', 'Anna', 'gammal@example.com', '" . self::OID . "')");

        $this->assertSame('a1', SsoAccounts::resolve($this->pdo, self::OID, 'ny@example.com', 'Anna A'));
    }
}
