<?php
namespace Tests;

use App\RateLimit;

final class SessionTest extends ApiTestCase
{
    private function seedUser(string $id, string $email, string $password): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (id, name, email, password_hash) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$id, 'Anna', $email, password_hash($password, PASSWORD_DEFAULT)]);
    }

    public function testLoginWithCorrectPasswordReturnsUser(): void
    {
        $this->seedUser('s1', 'anna@example.com', 'hemligt123');

        $res = $this->request('POST', '/api/session', [
            'email' => 'anna@example.com', 'password' => 'hemligt123',
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertSame('s1', $res['json']['id']);
        $this->assertFalse($res['json']['isAdmin']);
        $this->assertArrayNotHasKey('password_hash', $res['json']);
    }

    public function testUnknownEmailAndWrongPasswordGiveIdenticalResponses(): void
    {
        $this->seedUser('s2', 'bo@example.com', 'hemligt123');

        $wrong   = $this->request('POST', '/api/session', ['email' => 'bo@example.com', 'password' => 'fel']);
        $unknown = $this->request('POST', '/api/session', ['email' => 'finns.inte@example.com', 'password' => 'fel']);

        $this->assertSame($wrong['status'], $unknown['status']);
        $this->assertSame($wrong['json'], $unknown['json']);
        $this->assertSame(401, $wrong['status']);
    }

    public function testSixFailedAttemptsAreRateLimited(): void
    {
        $this->seedUser('s3', 'cia@example.com', 'hemligt123');

        for ($i = 0; $i < 5; $i++) {
            $this->request('POST', '/api/session', ['email' => 'cia@example.com', 'password' => 'fel']);
        }
        $res = $this->request('POST', '/api/session', ['email' => 'cia@example.com', 'password' => 'hemligt123']);

        $this->assertSame(429, $res['status']);
    }

    /**
     * Regressionstest: gamla felförsök rensades aldrig bort av en lyckad
     * inloggning. Fyra fel, en lyckad inloggning och sedan ÄNNU ett fel ger,
     * utan rensning, fem registrerade felförsök totalt — tillräckligt för att
     * nästa (korrekta) inloggning ska spärras med 429, trots att kontot
     * lyckades logga in mellan de två felserierna. Failar mot koden innan
     * fixen, där login_attempts-raderna blir kvar efter en lyckad
     * inloggning och det femte felet därför räknas in i samma fönster.
     */
    public function testSuccessfulLoginClearsPriorFailedAttempts(): void
    {
        $this->seedUser('s6', 'gustav@example.com', 'hemligt123');

        for ($i = 0; $i < 4; $i++) {
            $this->request('POST', '/api/session', ['email' => 'gustav@example.com', 'password' => 'fel']);
        }
        $success = $this->request('POST', '/api/session', ['email' => 'gustav@example.com', 'password' => 'hemligt123']);
        $this->assertSame(200, $success['status']);

        $this->request('POST', '/api/session', ['email' => 'gustav@example.com', 'password' => 'fel']);

        $again = $this->request('POST', '/api/session', ['email' => 'gustav@example.com', 'password' => 'hemligt123']);

        $this->assertSame(200, $again['status']);
    }

    public function testAccountWithoutPasswordHashRejectsPasswordLogin(): void
    {
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash)
                          VALUES ('s4', 'Dag', 'dag@example.com', NULL)");
        $this->seedUser('s4b', 'evert@example.com', 'hemligt123');

        $res = $this->request('POST', '/api/session', [
            'email' => 'dag@example.com', 'password' => 'vadsomhelst',
        ]);
        $wrong = $this->request('POST', '/api/session', [
            'email' => 'evert@example.com', 'password' => 'fel',
        ]);

        $this->assertSame(401, $res['status']);
        // Alla tre avvisningsgrenarna ska konvergera till samma svarskropp —
        // inte bara samma statuskod — annars blir endpointen en kontolista.
        $this->assertSame($wrong['json'], $res['json']);
    }

    public function testForwardedForIsIgnoredWhenSenderIsNotTrusted(): void
    {
        $ip = RateLimit::clientIp(
            ['trusted_proxies' => []],
            ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']
        );

        $this->assertNull($ip, 'Tom trusted_proxies ska stänga av per-IP helt.');
    }

    public function testForwardedForIsUsedWhenSenderIsTrusted(): void
    {
        $ip = RateLimit::clientIp(
            ['trusted_proxies' => ['10.0.0.5']],
            ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '9.9.9.9, 1.2.3.4']
        );

        $this->assertSame('1.2.3.4', $ip, 'Sista hoppet är det proxyn själv satte.');
    }

    public function testFiveRecordedAttemptsForOneIpRateLimitsThatIp(): void
    {
        $ip = '203.0.113.7';
        for ($i = 0; $i < 5; $i++) {
            RateLimit::record($this->pdo, "ip-attempt-$i@example.com", $ip);
        }

        // Annan e-post än de inspelade försöken — träffen kan bara komma
        // från IP-grenen (OR ip = ?), och inet_pton()-bindningen måste
        // rondtripa utan fel för att komma hit över huvud taget.
        $this->assertTrue(RateLimit::tooManyAttempts($this->pdo, 'nagon.annan@example.com', $ip));
    }

    public function testAttemptsForOneIpDoNotRateLimitAnotherIp(): void
    {
        $ipA = '203.0.113.7';
        $ipB = '203.0.113.8';
        for ($i = 0; $i < 5; $i++) {
            RateLimit::record($this->pdo, "ip-attempt-$i@example.com", $ipA);
        }

        $this->assertFalse(RateLimit::tooManyAttempts($this->pdo, 'nagon.annan@example.com', $ipB));
    }

    public function testMalformedForwardedHopNeverProducesGarbageIp(): void
    {
        $ip = RateLimit::clientIp(
            ['trusted_proxies' => ['10.0.0.5']],
            ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => 'not-an-ip']
        );

        // Får aldrig vara skräptexten — antingen null eller den validerade
        // REMOTE_ADDR, aldrig något som skickas vidare till inet_pton().
        $this->assertTrue(
            $ip === null || $ip === '10.0.0.5',
            'Ett trasigt hopp fick igenom ovaliderad text.'
        );
    }

    public function testChangePasswordRequiresCurrentPassword(): void
    {
        $this->seedUser('s5', 'eva@example.com', 'hemligt123');

        $bad = $this->request('PUT', '/api/session/password',
            ['currentPassword' => 'fel', 'newPassword' => 'nytt123456'], 's5');
        $this->assertSame(422, $bad['status']);

        $ok = $this->request('PUT', '/api/session/password',
            ['currentPassword' => 'hemligt123', 'newPassword' => 'nytt123456'], 's5');
        $this->assertSame(200, $ok['status']);

        $hash = $this->pdo->query("SELECT password_hash FROM users WHERE id = 's5'")->fetchColumn();
        $this->assertTrue(password_verify('nytt123456', $hash));
        $this->assertFalse(password_verify('hemligt123', $hash));
    }
}
