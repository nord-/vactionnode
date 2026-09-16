# PHP + MariaDB Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ersätt GitHub API-datalagret med ett PHP-backend mot MariaDB, med konton per person och server-side sessioner.

**Architecture:** En front controller (`public/api/index.php`) tar emot alla API-anrop, en routingtabell deklarerar behörighet per endpoint, och en vakt kör session- och behörighetskontroll före varje handler. Databasen håller garantierna (kaskader, unika nycklar, intervallcheck). Frontend behåller sina komponenter och sin vokabulär; översättningen till kolumnnamn sker i SQL-aliasen.

**Tech Stack:** PHP 8.2, PDO, MariaDB 10.11.11, Apache 2.4, Vue 3, Vite, Vitest, PHPUnit, Docker Compose.

**Spec:** `docs/superpowers/specs/2026-09-16-php-mariadb-backend-design.md`

## Global Constraints

- **Gren:** allt arbete sker på `feature/php-mariadb-backend`. Aldrig commit direkt på `main`.
- **PHP 8.2**, **MariaDB 10.11.11** (exakt pinnad image), **Apache 2.4**.
- **Inga körtidsberoenden.** Composer används enbart för PHPUnit; `vendor/` deployas aldrig.
- **Värden har varken `php` eller `composer` — bara Docker.** Allt PHP körs med `docker compose -f docker/docker-compose.yml exec -T php …`. Ett `composer`- eller `php`-kommando direkt i terminalen kommer inte att fungera.
- **Aldrig `exit` i PHP-koden.** Vakter och valideringar kastar `App\HttpException`, som `Router::dispatch()` gör om till JSON. Ett `exit` dödar PHPUnit-processen mitt i sviten.
- **Radslut CRLF, indentering med mellanslag** (se `.gitattributes`).
- **Svenska i UI-text och kodkommentarer.** Commit-meddelanden på engelska.
- **Conventional commits** (`feat:`, `fix:`, `test:`, `chore:`, `docs:`).
- **API:et talar frontendens vokabulär:** `persons`, `groups`, `personId`, `groupId`, `start`, `end`. Databasen använder `users`, `teams`, `user_id`, `team_id`, `start_date`, `end_date`. Översättningen sker med SQL-alias, aldrig i JavaScript.
- **`` `start` `` och `` `end` `` måste backtickas** som alias — `END` är reserverat i MariaDB.
- **Statuskoder:** `200`/`201` lyckat, `401` utan giltig session eller vid fel inloggningsuppgifter, `403` rätt till någon annans resurs saknas, `404` okänd resurs, `422` valideringsfel, `429` för många inloggningsförsök.
- **Felsvar:** `{"error":{"code":"...","message":"..."}}`. Aldrig stacktrace.
- **Testdatabas:** `vacation_test`. Varje test körs i en transaktion som rullas tillbaka.

---

## Filstruktur

**Backend (nytt):**

| Fil | Ansvar |
|---|---|
| `docker/docker-compose.yml` | PHP-Apache på 8080, MariaDB på 3307 |
| `docker/Dockerfile` | `php:8.2-apache` + `pdo_mysql` + `opcache` |
| `db/schema.sql` | Tabellerna `teams`, `users`, `vacations`, `login_attempts` |
| `db/create-admin.php` | CLI-omslag kring `Persons::create()` |
| `config.php.example` | Mall; `config.php` är gitignorerad |
| `app/Db.php` | PDO-anslutning med rätt options |
| `app/Uuid.php` | UUID v4 |
| `app/Http.php` | JSON in/ut, felformat |
| `app/Auth.php` | Session, vakten, ägarkontroll |
| `app/Router.php` | Routingtabell och dispatch |
| `app/Persons.php` | Kontoskapande, delat mellan handler och CLI |
| `app/Handlers/SessionHandler.php` | Inloggning, utloggning, eget lösenordsbyte |
| `app/Handlers/BootstrapHandler.php` | Startdata i en request |
| `app/Handlers/VacationHandler.php` | Semesterrader |
| `app/Handlers/PersonHandler.php` | Konton |
| `app/Handlers/GroupHandler.php` | Grupper |
| `public/api/index.php` | Front controller |
| `public/api/.htaccess` | Rewrite till `index.php` |

**Frontend (ändrat):**

| Fil | Åtgärd |
|---|---|
| `src/lib/apiClient.js` | Ny — `fetch`-omslag och felmappning |
| `src/lib/dataStore.js` | Skrivs om mot API:et |
| `src/lib/auth.js` | Skrivs om mot `/api/session` |
| `src/composables/useStore.js` | Lokal uppdatering i stället för full omladdning |
| `src/components/LoginView.vue` | E-postfält |
| `src/components/AdminPanel.vue` | E-post, lösenord, adminflagga |
| `src/components/ChangePasswordView.vue` | Ny |
| `src/App.vue` | Sessionsstart, döljer adminvyn |
| `src/config.js` | Krymper till API-bassökväg |
| `vite.config.js` | `base: '/'`, proxy mot `localhost:8080` |
| `src/lib/githubClient.js`, `localClient.js`, `identity.js` | Raderas |
| `src/components/PersonSelectView.vue` | Raderas |
| `data/registry.json`, `data/vacations.json` | Raderas |

---

### Task 1: Dev-miljö, schema och testriggen

**Files:**
- Create: `docker/docker-compose.yml`, `docker/Dockerfile`, `docker/vhost.conf`, `db/schema.sql`, `db/reset-test-db.sh`, `config.php.example`, `config.php`, `composer.json`, `phpunit.xml`, `tests-php/bootstrap.php`, `tests-php/DbTestCase.php`, `tests-php/SchemaTest.php`
- Modify: `.gitignore`, `.gitattributes`

**Värdmiljön har varken `php` eller `composer` — bara Docker.** Varje
PHP-kommando i planen körs därför inuti `php`-containern. Skriv aldrig
`composer test` rakt av; det finns inget att köra det med.

**Interfaces:**
- Consumes: ingenting.
- Produces: `DbTestCase` med `protected PDO $pdo` och automatisk transaktion per test. Alla senare backend-tester ärver den.

- [ ] **Step 1: Skapa Docker-miljön**

`docker/Dockerfile`:

```dockerfile
FROM php:8.2-apache

# Composer finns inte på värden, så den bor i imagen. vendor/ hamnar i
# den monterade repo-roten och deployas aldrig till NAS:en.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN docker-php-ext-install pdo_mysql \
 && docker-php-ext-enable opcache \
 && a2enmod rewrite
RUN printf 'opcache.enable=1\nopcache.validate_timestamps=1\n' \
      > /usr/local/etc/php/conf.d/opcache.ini
COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf
WORKDIR /var/www/html
```

`docker/vhost.conf`:

```apache
<VirtualHost *:80>
    DocumentRoot /var/www/html/public
    <Directory /var/www/html/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

`docker/docker-compose.yml`:

```yaml
services:
  php:
    build:
      context: ..
      dockerfile: docker/Dockerfile
    ports:
      - "8080:80"
    # Hela repo-roten monteras, inte enskilda kataloger. Det ger samma
    # relativa sökvägar som på NAS:en, och undviker Dockers klassiska fälla:
    # en bind-mount av en fil som inte finns skapar en KATALOG med det namnet.
    volumes:
      - ..:/var/www/html
    environment:
      DB_HOST: db
      DB_PORT: "3306"
    depends_on:
      - db
  db:
    image: mariadb:10.11.11
    environment:
      MARIADB_ROOT_PASSWORD: root
      MARIADB_DATABASE: vacation
    # 3307 utåt speglar NAS:en; inuti compose-nätet är porten 3306.
    ports:
      - "3307:3306"
    volumes:
      - dbdata:/var/lib/mysql
volumes:
  dbdata:
```

- [ ] **Step 2: Skriv schemat**

`db/schema.sql` — ordningen spelar roll: `teams` före `users` före `vacations`, på grund av de främmande nycklarna.

```sql
CREATE TABLE IF NOT EXISTS teams (
  id         CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  name       VARCHAR(100) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id            CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(255) NOT NULL UNIQUE,
  sso_id        VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL UNIQUE,
  password_hash VARCHAR(255) NULL,
  is_admin      TINYINT(1) NOT NULL DEFAULT 0,
  team_id       CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_team FOREIGN KEY (team_id)
    REFERENCES teams(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vacations (
  id         CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  user_id    CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  start_date DATE NOT NULL,
  end_date   DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_vac_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT ck_vac_range CHECK (end_date >= start_date),
  INDEX ix_vac_user (user_id),
  INDEX ix_vac_range (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(255) NOT NULL,
  ip           VARBINARY(16) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_attempt_email (email, attempted_at),
  INDEX ix_attempt_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 3: Konfigurationsmall**

`config.php.example`:

```php
<?php
// Kopiera till config.php och fyll i. config.php är gitignorerad.
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3307,
        'name' => 'vacation',
        'user' => 'vacation',
        'pass' => '',
    ],
    // Tomma listan stänger av per-IP-spärren. Fyll i reverse-proxyns
    // adress för att aktivera den — se spec §4.
    'trusted_proxies' => [],

    // Sessionscookiens Secure-flagga. ALLTID true i produktion. Sätts till
    // false enbart i den lokala Docker-miljön, som kör ren HTTP — annars
    // sätter browsern aldrig cookien och inget flöde går att testa.
    // $_SERVER['HTTPS'] duger inte som automatik: den är tom bakom DSM:s
    // reverse proxy, vilket hade stängt av Secure i produktion.
    'cookie_secure' => true,
];
```

Skapa dessutom `config.php` för utvecklingsmiljön — den är gitignorerad, men
**måste finnas innan `docker compose up`**:

```php
<?php
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'db',
        'port' => (int) (getenv('DB_PORT') ?: 3306),
        'name' => 'vacation',
        'user' => 'root',
        'pass' => 'root',
    ],
    'trusted_proxies' => [],
    'cookie_secure'   => false,
];
```

Lägg till i `.gitignore`: `config.php`, `vendor/`, `dist/`, `.env`.

Lägg till i `.gitattributes`, **efter** den befintliga `* text=auto eol=crlf`-raden
så att den mer specifika regeln vinner:

```gitattributes
*.sh text eol=lf
```

Utan den raden får `scripts/deploy.sh` och `db/reset-test-db.sh` CRLF, och bash
svarar `bad interpreter: /usr/bin/env bash^M`.

- [ ] **Step 4: Composer och PHPUnit**

`composer.json`:

```json
{
  "require-dev": { "phpunit/phpunit": "^10.5" },
  "autoload": { "psr-4": { "App\\": "app/" } },
  "autoload-dev": { "psr-4": { "Tests\\": "tests-php/" } },
  "scripts": { "test": "phpunit" }
}
```

`phpunit.xml`:

```xml
<?xml version="1.0"?>
<phpunit bootstrap="tests-php/bootstrap.php" colors="true">
  <testsuites>
    <testsuite name="api">
      <directory>tests-php</directory>
    </testsuite>
  </testsuites>
</phpunit>
```

- [ ] **Step 5: Testbas med transaktion per test**

`tests-php/bootstrap.php`:

```php
<?php
require __DIR__ . '/../vendor/autoload.php';
```

`tests-php/DbTestCase.php`:

```php
<?php
namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;

abstract class DbTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        // Testerna körs inuti php-containern, där databasen heter db:3306.
        // Miljövariablerna gör det möjligt att peka om utan kodändring.
        $host = getenv('DB_HOST') ?: 'db';
        $port = getenv('DB_PORT') ?: '3306';

        $this->pdo = new PDO(
            "mysql:host={$host};port={$port};dbname=vacation_test;charset=utf8mb4",
            'root',
            'root',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_FOUND_ROWS   => true,
            ]
        );
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->pdo->rollBack();
    }
}
```

- [ ] **Step 6: Skriv det failande schematestet**

`tests-php/SchemaTest.php`:

```php
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
```

- [ ] **Step 7: Starta miljön och installera PHPUnit**

```bash
docker compose -f docker/docker-compose.yml up -d --build
docker compose -f docker/docker-compose.yml exec -T php composer install
```

- [ ] **Step 8: Kör testet och se att det failar**

Kör: `docker compose -f docker/docker-compose.yml exec -T php composer test`
Förväntat: FAIL — `vacation_test` finns inte och tabellerna saknas.

- [ ] **Step 9: Skapa testdatabasen och applicera schemat**

`db/reset-test-db.sh` (LF-radslut, se `.gitattributes` i steg 3):

```bash
#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
DC="docker compose -f docker/docker-compose.yml"

$DC exec -T db mariadb -uroot -proot \
  -e "DROP DATABASE IF EXISTS vacation_test; CREATE DATABASE vacation_test"
$DC exec -T db mariadb -uroot -proot vacation_test < db/schema.sql
```

Kör: `bash db/reset-test-db.sh`

- [ ] **Step 10: Kör testet igen**

Kör: `docker compose -f docker/docker-compose.yml exec -T php composer test`
Förväntat: PASS — tre gröna tester.

- [ ] **Step 11: Commit**

```bash
git add docker db config.php.example composer.json composer.lock phpunit.xml tests-php .gitignore .gitattributes
git commit -m "chore: add Docker development environment, database schema and PHPUnit harness"
```

---

### Task 2: Db, Uuid och Http

**Files:**
- Create: `app/Db.php`, `app/Uuid.php`, `app/Http.php`, `app/HttpException.php`, `tests-php/DbTest.php`
- Test: `tests-php/DbTest.php`

**Interfaces:**
- Consumes: `Tests\DbTestCase` från Task 1.
- Produces:
  - `App\Db::connect(array $config): PDO`
  - `App\Uuid::v4(): string`
  - `App\Http::json(mixed $data, int $status = 200): void`
  - `App\Http::error(string $code, string $message, int $status): void`
  - `App\Http::body(): array`
  - `App\HttpException(string $code, string $message, int $status)` — kastas i stället för `exit`

- [ ] **Step 1: Skriv det failande testet**

`tests-php/DbTest.php`:

```php
<?php
namespace Tests;

use App\Db;
use App\Uuid;

final class DbTest extends DbTestCase
{
    public function testUpdateReportsMatchedRowsNotChangedRows(): void
    {
        $pdo = Db::connect([
            'host' => '127.0.0.1', 'port' => 3307, 'name' => 'vacation_test',
            'user' => 'root', 'pass' => 'root',
        ]);
        // Egen anslutning, alltså utanför testets transaktion. Städningen
        // måste därför ske i finally, annars blir raden kvar och nästa körning
        // faller på UNIQUE(email).
        $pdo->exec("INSERT INTO users (id, name, email, password_hash)
                    VALUES ('fr1', 'Anna', 'fr1@example.com', 'x')");

        try {
            // Samma värde som redan står där: noll ändrade rader, en matchad.
            $stmt = $pdo->prepare('UPDATE users SET name = ? WHERE id = ?');
            $stmt->execute(['Anna', 'fr1']);

            $this->assertSame(1, $stmt->rowCount());
        } finally {
            $pdo->exec("DELETE FROM users WHERE id = 'fr1'");
        }
    }

    public function testUuidV4HasCorrectShape(): void
    {
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            Uuid::v4()
        );
    }
}
```

- [ ] **Step 2: Kör testet och se att det failar**

Kör: `docker compose -f docker/docker-compose.yml exec -T php composer test -- --filter DbTest`
Förväntat: FAIL med `Class "App\Db" not found`.

- [ ] **Step 3: Implementera Db och Uuid**

`app/Db.php`:

```php
<?php
namespace App;

use PDO;

final class Db
{
    public static function connect(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'], $config['port'], $config['name']
        );

        // MYSQL_ATTR_FOUND_ROWS MÅSTE ligga här, i konstruktorns options.
        // Ett setAttribute() efter att anslutningen öppnats ignoreras tyst,
        // och rowCount() räknar då ändrade rader i stället för matchade.
        return new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_FOUND_ROWS   => true,
        ]);
    }
}
```

`app/Uuid.php`:

```php
<?php
namespace App;

final class Uuid
{
    public static function v4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
```

- [ ] **Step 4: Implementera Http**

`app/Http.php`:

```php
<?php
namespace App;

final class Http
{
    // use App\HttpException; behövs inte — samma namnrymd.
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function error(string $code, string $message, int $status): void
    {
        self::json(['error' => ['code' => $code, 'message' => $message]], $status);
    }

    /**
     * Läser och avkodar request-body:n. Kravet på application/json är en del
     * av CSRF-skyddet: ett formulär från en annan sajt kan inte sätta headern
     * utan preflight, och preflighten faller på CORS.
     */
    public static function body(): array
    {
        $type = $_SERVER['CONTENT_TYPE'] ?? '';
        if (!str_starts_with($type, 'application/json')) {
            // Kastar, aldrig exit: front controllern körs i samma process som
            // PHPUnit, och ett exit hade dödat hela testsviten vid första
            // felsvaret i stället för att låta testet göra sin assertion.
            throw new HttpException('unsupported_media_type', 'Kräver application/json.', 415);
        }

        $decoded = json_decode(file_get_contents('php://input') ?: '', true);

        return is_array($decoded) ? $decoded : [];
    }
}
```

- [ ] **Step 5: Implementera HttpException**

`app/HttpException.php`:

```php
<?php
namespace App;

use RuntimeException;

/**
 * Avbryter en request med en given statuskod. Router::dispatch() fångar den
 * och gör den till ett JSON-felsvar.
 *
 * Varför ett undantag och inte exit: ApiTestCase kör routern i samma process
 * som PHPUnit. Ett exit i en vakt eller en validering hade avslutat hela
 * testsviten vid första 403:an, utan rapport och utan resterande tester.
 */
final class HttpException extends RuntimeException
{
    public function __construct(
        public readonly string $code,
        string $message,
        public readonly int $status
    ) {
        parent::__construct($message);
    }
}
```

- [ ] **Step 6: Kör testet och se att det passerar**

Kör: `docker compose -f docker/docker-compose.yml exec -T php composer test -- --filter DbTest`
Förväntat: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Db.php app/Uuid.php app/Http.php app/HttpException.php tests-php/DbTest.php
git commit -m "feat: add PDO connection, UUID generation and JSON response helpers"
```

---

### Task 3: Router, vakten och front controller

**Files:**
- Create: `app/Auth.php`, `app/Router.php`, `public/api/index.php`, `public/api/.htaccess`, `tests-php/ApiTestCase.php`, `tests-php/RouterTest.php`
- Test: `tests-php/RouterTest.php`

**Interfaces:**
- Consumes: `App\Db`, `App\Http` från Task 2.
- Produces:
  - `App\Auth::start(array $config): void`
  - `App\Auth::currentUser(PDO $pdo): ?array` — `['id','name','email','groupId','isAdmin']` eller `null`
  - `App\Auth::requireOwner(PDO $pdo, string $vacationId, array $user): void` — kastar `HttpException` med `404`/`403` vid miss
  - `App\Router::routes(): array` — lista av `['method','pattern','class','action','access','keepSession']`
  - `App\Router::dispatch(PDO $pdo, string $method, string $path): void`
  - `Tests\ApiTestCase::request(string $method, string $path, ?array $body = null, ?string $asUserId = null): array` — returnerar `['status' => int, 'json' => array]`

**Åtkomstnivåer:** `public` (ingen session), `user` (inloggad), `admin` (adminflagga), `owner` (ägare av `{id}` i `vacations`, eller admin).

- [ ] **Step 1: Skriv det failande testet**

`tests-php/RouterTest.php`:

```php
<?php
namespace Tests;

final class RouterTest extends ApiTestCase
{
    public function testUnknownRouteReturnsJsonNotFound(): void
    {
        $res = $this->request('GET', '/api/nonsense');

        $this->assertSame(404, $res['status']);
        $this->assertSame('not_found', $res['json']['error']['code']);
    }

    public function testProtectedRouteWithoutSessionReturnsUnauthorized(): void
    {
        $res = $this->request('GET', '/api/bootstrap');

        $this->assertSame(401, $res['status']);
        $this->assertSame('unauthenticated', $res['json']['error']['code']);
    }

    public function testErrorBodyNeverContainsStackTrace(): void
    {
        $res = $this->request('GET', '/api/nonsense');

        $this->assertArrayNotHasKey('trace', $res['json']);
        $this->assertSame(['code', 'message'], array_keys($res['json']['error']));
    }
}
```

- [ ] **Step 2: Skriv testriggen som anropar routern i process**

`tests-php/ApiTestCase.php`:

```php
<?php
namespace Tests;

use App\Http;
use App\Router;

/**
 * Kör routern i samma process som testet. Sessionen simuleras genom att
 * $_SESSION sätts direkt — det håller testerna snabba och oberoende av
 * en körande webbserver.
 */
abstract class ApiTestCase extends DbTestCase
{
    protected function request(
        string $method,
        string $path,
        ?array $body = null,
        ?string $asUserId = null
    ): array {
        $_SESSION = $asUserId === null ? [] : ['user_id' => $asUserId];
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        // Routingmönstren är ankrade med $, så en query-sträng i sökvägen
        // matchar aldrig. Den måste bort ur sökvägen och in i $_GET, precis
        // som front controllern gör med parse_url().
        $parts = parse_url($path);
        $_GET = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $_GET);
        }

        Http::setTestBody($body === null ? '' : json_encode($body));

        ob_start();
        Router::dispatch($this->pdo, $method, $parts['path']);
        $out = ob_get_clean();

        return [
            'status' => Http::lastStatus(),
            'json'   => json_decode($out ?: '{}', true) ?? [],
        ];
    }
}
```

`App\Http` behöver därför två testkrokar. Lägg till i `app/Http.php`:

```php
    private static ?string $testBody = null;
    private static int $lastStatus = 200;

    public static function setTestBody(?string $body): void
    {
        self::$testBody = $body;
    }

    public static function lastStatus(): int
    {
        return self::$lastStatus;
    }
```

Ändra `json()` till att sätta `self::$lastStatus = $status;` och anropa
`http_response_code($status)` endast när `PHP_SAPI !== 'cli'`. Ändra `body()`
till att läsa `self::$testBody ?? file_get_contents('php://input')`.

- [ ] **Step 3: Kör testet och se att det failar**

Kör: `docker compose -f docker/docker-compose.yml exec -T php composer test -- --filter RouterTest`
Förväntat: FAIL med `Class "App\Router" not found`.

- [ ] **Step 4: Implementera vakten**

`app/Auth.php`:

```php
<?php
namespace App;

use PDO;

final class Auth
{
    public static function start(array $config): void
    {
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Secure läses ur config, aldrig ur $_SERVER['HTTPS'] — den är tom
        // bakom DSM:s reverse proxy och hade stängt av flaggan i produktion.
        // Default är true; bara dev-config.php sätter false.
        session_set_cookie_params([
            'httponly' => true,
            'secure'   => $config['cookie_secure'] ?? true,
            'samesite' => 'Strict',
            'path'     => '/',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();
    }

    public static function currentUser(PDO $pdo): ?array
    {
        $id = $_SESSION['user_id'] ?? null;
        if ($id === null) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT id, name, email, team_id AS groupId, is_admin AS isAdmin
             FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $row['isAdmin'] = (bool) $row['isAdmin'];

        return $row;
    }

    /**
     * Slår upp raden innan handlern körs. Det är uppslaget som gör att 404
     * och 403 går att skilja åt — en UPDATE som påverkar noll rader kan inte
     * säga om raden saknas eller tillhör någon annan.
     */
    public static function requireOwner(PDO $pdo, string $vacationId, array $user): void
    {
        $stmt = $pdo->prepare('SELECT user_id FROM vacations WHERE id = ?');
        $stmt->execute([$vacationId]);
        $ownerId = $stmt->fetchColumn();

        if ($ownerId === false) {
            throw new HttpException('not_found', 'Semesterraden finns inte.', 404);
        }
        if ($ownerId !== $user['id'] && !$user['isAdmin']) {
            throw new HttpException('forbidden', 'Du får bara ändra din egen semester.', 403);
        }
    }
}
```

- [ ] **Step 5: Implementera routern**

`app/Router.php`:

```php
<?php
namespace App;

use PDO;

final class Router
{
    /**
     * keepSession = true för de endpoints som skriver till sessionen.
     * session_write_close() får inte köras före dem: inloggningen anropar
     * session_regenerate_id(true), vilket inte fungerar på en stängd session.
     */
    public static function routes(): array
    {
        return [
            ['POST',   '#^/api/session$#',              Handlers\SessionHandler::class,   'login',          'public', true],
            ['GET',    '#^/api/session$#',              Handlers\SessionHandler::class,   'me',             'user',   false],
            ['DELETE', '#^/api/session$#',              Handlers\SessionHandler::class,   'logout',         'user',   true],
            ['PUT',    '#^/api/session/password$#',     Handlers\SessionHandler::class,   'changePassword', 'user',   true],
            ['GET',    '#^/api/bootstrap$#',            Handlers\BootstrapHandler::class, 'index',          'user',   false],
            ['POST',   '#^/api/vacations$#',            Handlers\VacationHandler::class,  'create',         'user',   false],
            ['PUT',    '#^/api/vacations/([\w-]+)$#',   Handlers\VacationHandler::class,  'update',         'owner',  false],
            ['DELETE', '#^/api/vacations/([\w-]+)$#',   Handlers\VacationHandler::class,  'destroy',        'owner',  false],
            ['POST',   '#^/api/persons$#',              Handlers\PersonHandler::class,    'create',         'admin',  false],
            ['PUT',    '#^/api/persons/([\w-]+)$#',     Handlers\PersonHandler::class,    'update',         'admin',  false],
            ['DELETE', '#^/api/persons/([\w-]+)$#',     Handlers\PersonHandler::class,    'destroy',        'admin',  false],
            ['POST',   '#^/api/groups$#',               Handlers\GroupHandler::class,     'create',         'admin',  false],
            ['PUT',    '#^/api/groups/([\w-]+)$#',      Handlers\GroupHandler::class,     'update',         'admin',  false],
            ['DELETE', '#^/api/groups/([\w-]+)$#',      Handlers\GroupHandler::class,     'destroy',        'admin',  false],
        ];
    }

    public static function dispatch(PDO $pdo, string $method, string $path): void
    {
        foreach (self::routes() as [$m, $pattern, $class, $action, $access, $keepSession]) {
            if ($m !== $method || !preg_match($pattern, $path, $matches)) {
                continue;
            }

            // Ett enda try runt hela dispatchen. Vakter och valideringar kastar
            // HttpException i stället för att anropa exit, så att routern kan
            // omvandla dem till JSON — och så att PHPUnit överlever ett 403-svar.
            try {
                $user = Auth::currentUser($pdo);

                if ($access !== 'public' && $user === null) {
                    throw new HttpException('unauthenticated', 'Du måste logga in.', 401);
                }
                if (!$keepSession && PHP_SAPI !== 'cli') {
                    session_write_close();
                }
                if ($access === 'admin' && !$user['isAdmin']) {
                    throw new HttpException('forbidden', 'Kräver administratör.', 403);
                }
                if ($access === 'owner') {
                    Auth::requireOwner($pdo, $matches[1], $user);
                }

                (new $class($pdo))->$action($user, $matches[1] ?? null);
            } catch (HttpException $e) {
                Http::error($e->code, $e->getMessage(), $e->status);
            }

            return;
        }

        Http::error('not_found', 'Okänd resurs.', 404);
    }
}
```

- [ ] **Step 6: Implementera front controllern**

`public/api/index.php`:

```php
<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = dirname(__DIR__, 2) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$config = require dirname(__DIR__, 2) . '/config.php';

App\Auth::start($config);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

try {
    App\Router::dispatch(
        App\Db::connect($config['db']),
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        rtrim($path, '/') ?: '/'
    );
} catch (Throwable $e) {
    error_log((string) $e);
    App\Http::error('server_error', 'Något gick fel.', 500);
}
```

`public/api/.htaccess`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,L]
```

- [ ] **Step 7: Skapa tomma handlerklasser så routern kan laddas**

Skapa `app/Handlers/SessionHandler.php`, `BootstrapHandler.php`, `VacationHandler.php`, `PersonHandler.php` och `GroupHandler.php`, var och en med konstruktorn och en `index`-metod som svarar `501`:

```php
<?php
namespace App\Handlers;

use App\Http;
use PDO;

final class SessionHandler
{
    public function __construct(private PDO $pdo) {}

    public function login(?array $user, ?string $id = null): void
    {
        Http::error('not_implemented', 'Inte implementerad än.', 501);
    }
}
```

Upprepa mönstret för varje metod som `Router::routes()` refererar: `me`, `logout`, `changePassword`, `index`, `create`, `update`, `destroy`.

- [ ] **Step 8: Kör testet och se att det passerar**

Kör: `docker compose -f docker/docker-compose.yml exec -T php composer test -- --filter RouterTest`
Förväntat: PASS — tre gröna tester.

- [ ] **Step 9: Verifiera AllowOverride i Docker**

```bash
docker compose -f docker/docker-compose.yml up -d
curl -i http://localhost:8080/api/session
```

Förväntat: `401` med JSON-body. Får du `404` från Apache är `AllowOverride` inte aktivt — **stanna och rapportera**, eftersom spec §11 då kräver ett arkitekturbyte till en fil per resurs.

- [ ] **Step 10: Commit**

```bash
git add app public tests-php
git commit -m "feat: add front controller, routing table and session guard"
```

---

### Task 4: Sessionshandlern med rate limiting

**Files:**
- Modify: `app/Handlers/SessionHandler.php`
- Create: `app/RateLimit.php`, `tests-php/SessionTest.php`
- Test: `tests-php/SessionTest.php`

**Interfaces:**
- Consumes: `App\Auth`, `App\Http`, `App\Db`, `Tests\ApiTestCase`.
- Produces:
  - `App\RateLimit::tooManyAttempts(PDO $pdo, string $email, ?string $ip): bool`
  - `App\RateLimit::record(PDO $pdo, string $email, ?string $ip): void`
  - `App\RateLimit::clientIp(array $config, array $server): ?string`
  - `SessionHandler::login/me/logout/changePassword`

- [ ] **Step 1: Skriv de failande testerna**

`tests-php/SessionTest.php`:

```php
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

    public function testAccountWithoutPasswordHashRejectsPasswordLogin(): void
    {
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash)
                          VALUES ('s4', 'Dag', 'dag@example.com', NULL)");

        $res = $this->request('POST', '/api/session', [
            'email' => 'dag@example.com', 'password' => 'vadsomhelst',
        ]);

        $this->assertSame(401, $res['status']);
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
```

- [ ] **Step 2: Kör testerna och se att de failar**

Kör: `docker compose -f docker/docker-compose.yml exec -T php composer test -- --filter SessionTest`
Förväntat: FAIL — `501 not_implemented` från stubben.

- [ ] **Step 3: Implementera rate limiting**

`app/RateLimit.php`:

```php
<?php
namespace App;

use PDO;

final class RateLimit
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;

    /**
     * X-Forwarded-For är klientstyrd och trivial att förfalska. Headern läses
     * därför bara när avsändaren själv står i trusted_proxies, och då används
     * sista hoppet — det är det enda som proxyn själv har satt.
     * Tom lista stänger av per-IP-spärren, vilket är standard: bakom DSM:s
     * reverse proxy är REMOTE_ADDR proxyns adress, och en spärr på den skulle
     * låsa ute alla samtidigt.
     */
    public static function clientIp(array $config, array $server): ?string
    {
        $trusted = $config['trusted_proxies'] ?? [];
        if ($trusted === []) {
            return null;
        }

        $remote = $server['REMOTE_ADDR'] ?? null;
        if ($remote === null || !in_array($remote, $trusted, true)) {
            return $remote;
        }

        $hops = array_map('trim', explode(',', $server['HTTP_X_FORWARDED_FOR'] ?? ''));
        $last = end($hops);

        return $last === '' ? $remote : $last;
    }

    public static function tooManyAttempts(PDO $pdo, string $email, ?string $ip): bool
    {
        self::purge($pdo);

        $sql = 'SELECT COUNT(*) FROM login_attempts
                WHERE attempted_at > NOW() - INTERVAL ? MINUTE AND (email = ?';
        $params = [self::WINDOW_MINUTES, $email];

        if ($ip !== null) {
            $sql .= ' OR ip = ?';
            $params[] = inet_pton($ip);
        }
        $sql .= ')';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    public static function record(PDO $pdo, string $email, ?string $ip): void
    {
        $stmt = $pdo->prepare('INSERT INTO login_attempts (email, ip) VALUES (?, ?)');
        $stmt->execute([$email, $ip === null ? '' : inet_pton($ip)]);
    }

    /** Opportunistisk gallring — inget schemalagt jobb behövs. */
    private static function purge(PDO $pdo): void
    {
        $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY');
    }
}
```

- [ ] **Step 4: Implementera sessionshandlern**

`app/Handlers/SessionHandler.php`:

```php
<?php
namespace App\Handlers;

use App\Http;
use App\RateLimit;
use PDO;

final class SessionHandler
{
    public function __construct(private PDO $pdo) {}

    public function login(?array $user, ?string $id = null): void
    {
        $body     = Http::body();
        $email    = strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');
        $config   = require dirname(__DIR__, 2) . '/config.php';
        $ip       = RateLimit::clientIp($config, $_SERVER);

        if (RateLimit::tooManyAttempts($this->pdo, $email, $ip)) {
            Http::error('too_many_attempts', 'För många försök. Vänta en stund.', 429);
            return;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, team_id AS groupId, is_admin AS isAdmin, password_hash
             FROM users WHERE email = ?'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        // Samma svar oavsett om kontot saknas, saknar lösenord eller har fel
        // lösenord — annars blir endpointen en kontolista.
        if ($row === false || $row['password_hash'] === null
            || !password_verify($password, $row['password_hash'])) {
            RateLimit::record($this->pdo, $email, $ip);
            Http::error('invalid_credentials', 'Fel e-post eller lösenord.', 401);
            return;
        }

        // Guardad som logout: i CLI (PHPUnit) finns ingen aktiv session, och
        // session_regenerate_id() ger då en PHP-varning som gör testet rött.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $row['id'];

        unset($row['password_hash']);
        $row['isAdmin'] = (bool) $row['isAdmin'];
        Http::json($row);
    }

    public function me(?array $user, ?string $id = null): void
    {
        Http::json($user);
    }

    public function logout(?array $user, ?string $id = null): void
    {
        $_SESSION = [];
        if (PHP_SAPI !== 'cli') {
            session_destroy();
        }
        Http::json(['ok' => true]);
    }

    public function changePassword(?array $user, ?string $id = null): void
    {
        $body    = Http::body();
        $current = (string) ($body['currentPassword'] ?? '');
        $new     = (string) ($body['newPassword'] ?? '');

        if (strlen($new) < 8) {
            Http::error('validation_failed', 'Nytt lösenord måste vara minst 8 tecken.', 422);
            return;
        }

        $stmt = $this->pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hash = $stmt->fetchColumn();

        if ($hash === null || !password_verify($current, $hash)) {
            Http::error('validation_failed', 'Nuvarande lösenord stämmer inte.', 422);
            return;
        }

        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);

        Http::json(['ok' => true]);
    }
}
```

- [ ] **Step 5: Kör testerna och se att de passerar**

Kör: `docker compose -f docker/docker-compose.yml exec -T php composer test -- --filter SessionTest`
Förväntat: PASS — sju gröna tester.

- [ ] **Step 6: Commit**

```bash
git add app tests-php
git commit -m "feat: add session endpoints with rate limiting and password change"
```

---

### Task 5: Bootstrap-endpointen

**Files:**
- Modify: `app/Handlers/BootstrapHandler.php`
- Create: `tests-php/BootstrapTest.php`
- Test: `tests-php/BootstrapTest.php`

**Interfaces:**
- Consumes: `App\Http`, `Tests\ApiTestCase`.
- Produces: `GET /api/bootstrap?from=&to=` → `{me, groups, persons, vacations}` i frontendens vokabulär.

- [ ] **Step 1: Skriv de failande testerna**

`tests-php/BootstrapTest.php`:

```php
<?php
namespace Tests;

final class BootstrapTest extends ApiTestCase
{
    private function seed(): void
    {
        $this->pdo->exec("INSERT INTO teams (id, name) VALUES ('t1', 'Team A')");
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash, team_id)
                          VALUES ('b1', 'Anna', 'anna@example.com', 'x', 't1')");
        // Börjar före intervallet, slutar inuti det.
        $this->pdo->exec("INSERT INTO vacations (id, user_id, start_date, end_date)
                          VALUES ('v1', 'b1', '2026-05-25', '2026-06-03')");
        // Helt utanför intervallet.
        $this->pdo->exec("INSERT INTO vacations (id, user_id, start_date, end_date)
                          VALUES ('v2', 'b1', '2026-09-01', '2026-09-05')");
    }

    public function testResponseUsesFrontendVocabulary(): void
    {
        $this->seed();

        $res = $this->request('GET', '/api/bootstrap?from=2026-06-01&to=2026-06-30', null, 'b1');

        $this->assertSame(200, $res['status']);
        $this->assertSame('b1', $res['json']['me']['id']);
        $this->assertSame('t1', $res['json']['persons'][0]['groupId']);
        $this->assertSame('b1', $res['json']['vacations'][0]['personId']);
        $this->assertSame('2026-05-25', $res['json']['vacations'][0]['start']);
        $this->assertSame('2026-06-03', $res['json']['vacations'][0]['end']);
    }

    public function testFilterIsOverlapNotContainment(): void
    {
        $this->seed();

        $res = $this->request('GET', '/api/bootstrap?from=2026-06-01&to=2026-06-30', null, 'b1');

        $ids = array_column($res['json']['vacations'], 'id');
        $this->assertContains('v1', $ids, 'Överlappande semester måste komma med.');
        $this->assertNotContains('v2', $ids);
    }

    public function testEmptyDatasetReturnsEmptyListsNotError(): void
    {
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash)
                          VALUES ('b2', 'Bo', 'bo@example.com', 'x')");

        $res = $this->request('GET', '/api/bootstrap?from=2026-06-01&to=2026-06-30', null, 'b2');

        $this->assertSame(200, $res['status']);
        $this->assertSame([], $res['json']['groups']);
        $this->assertSame([], $res['json']['vacations']);
    }
}
```

- [ ] **Step 2: Kör testerna och se att de failar**

Kör: `docker compose -f docker/docker-compose.yml exec -T php composer test -- --filter BootstrapTest`
Förväntat: FAIL — `501`.

- [ ] **Step 3: Implementera handlern**

`app/Handlers/BootstrapHandler.php`:

```php
<?php
namespace App\Handlers;

use App\Http;
use PDO;

final class BootstrapHandler
{
    public function __construct(private PDO $pdo) {}

    public function index(?array $user, ?string $id = null): void
    {
        $from = $_GET['from'] ?? date('Y-m-01');
        $to   = $_GET['to']   ?? date('Y-m-t');

        $groups = $this->pdo
            ->query('SELECT id, name FROM teams ORDER BY name')
            ->fetchAll();

        $persons = $this->pdo
            ->query('SELECT id, name, email, team_id AS groupId, is_admin AS isAdmin
                     FROM users ORDER BY name')
            ->fetchAll();

        foreach ($persons as &$p) {
            $p['isAdmin'] = (bool) $p['isAdmin'];
        }
        unset($p);

        // Överlapp, inte inneslutning: en semester som börjar före intervallet
        // och slutar inuti det måste synas i kalendern.
        // `start` och `end` backtickas — END är reserverat i MariaDB.
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id AS personId, start_date AS `start`, end_date AS `end`
             FROM vacations
             WHERE start_date <= ? AND end_date >= ?
             ORDER BY start_date'
        );
        $stmt->execute([$to, $from]);

        Http::json([
            'me'        => $user,
            'groups'    => $groups,
            'persons'   => $persons,
            'vacations' => $stmt->fetchAll(),
        ]);
    }
}
```

- [ ] **Step 4: Kör testerna och se att de passerar**

Kör: `docker compose -f docker/docker-compose.yml exec -T php composer test -- --filter BootstrapTest`
Förväntat: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Handlers/BootstrapHandler.php tests-php/BootstrapTest.php
git commit -m "feat: add bootstrap endpoint with overlap date filter"
```

---

### Task 6: Semesterhandlern och vakt-matrisen

**Files:**
- Modify: `app/Handlers/VacationHandler.php`
- Create: `tests-php/VacationTest.php`, `tests-php/GuardMatrixTest.php`
- Test: båda ovan

**Interfaces:**
- Consumes: `App\Auth::requireOwner`, `App\Uuid`, `App\Http`.
- Produces: `POST /api/vacations`, `PUT` och `DELETE /api/vacations/{id}`. Svaret på `POST` och `PUT` är den skapade eller ändrade raden i frontendens vokabulär.

- [ ] **Step 1: Skriv de failande testerna**

`tests-php/VacationTest.php`:

```php
<?php
namespace Tests;

final class VacationTest extends ApiTestCase
{
    private function seedTwoUsers(): void
    {
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash, is_admin) VALUES
            ('owner', 'Anna', 'anna@example.com', 'x', 0),
            ('other', 'Bo',   'bo@example.com',   'x', 0),
            ('admin', 'Cia',  'cia@example.com',  'x', 1)");
        $this->pdo->exec("INSERT INTO vacations (id, user_id, start_date, end_date)
                          VALUES ('vac1', 'owner', '2026-06-16', '2026-06-20')");
    }

    public function testCreateUsesSessionUserForNonAdmin(): void
    {
        $this->seedTwoUsers();

        $res = $this->request('POST', '/api/vacations',
            ['personId' => 'other', 'start' => '2026-07-01', 'end' => '2026-07-05'], 'owner');

        $this->assertSame(201, $res['status']);
        $this->assertSame('owner', $res['json']['personId'], 'personId får inte gå att förfalska.');
    }

    public function testAdminCanCreateForSomeoneElse(): void
    {
        $this->seedTwoUsers();

        $res = $this->request('POST', '/api/vacations',
            ['personId' => 'other', 'start' => '2026-07-01', 'end' => '2026-07-05'], 'admin');

        $this->assertSame(201, $res['status']);
        $this->assertSame('other', $res['json']['personId']);
    }

    public function testUnchangedDatesStillReturnOk(): void
    {
        $this->seedTwoUsers();

        $res = $this->request('PUT', '/api/vacations/vac1',
            ['start' => '2026-06-16', 'end' => '2026-06-20'], 'owner');

        $this->assertSame(200, $res['status'],
            'Kräver PDO::MYSQL_ATTR_FOUND_ROWS i Db::connect().');
    }

    public function testUnknownIdGives404AndSomeoneElsesGives403(): void
    {
        $this->seedTwoUsers();

        $missing = $this->request('PUT', '/api/vacations/finns-inte',
            ['start' => '2026-06-16', 'end' => '2026-06-20'], 'other');
        $this->assertSame(404, $missing['status']);

        $forbidden = $this->request('PUT', '/api/vacations/vac1',
            ['start' => '2026-06-16', 'end' => '2026-06-20'], 'other');
        $this->assertSame(403, $forbidden['status']);
    }

    public function testReversedRangeIsRejected(): void
    {
        $this->seedTwoUsers();

        $res = $this->request('POST', '/api/vacations',
            ['start' => '2026-07-05', 'end' => '2026-07-01'], 'owner');

        $this->assertSame(422, $res['status']);
    }
}
```

`tests-php/GuardMatrixTest.php` — vakt-matrisen ur spec §10:

```php
<?php
namespace Tests;

final class GuardMatrixTest extends ApiTestCase
{
    private function seed(): void
    {
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash, is_admin) VALUES
            ('g-owner', 'Anna', 'ga@example.com', 'x', 0),
            ('g-other', 'Bo',   'gb@example.com', 'x', 0),
            ('g-admin', 'Cia',  'gc@example.com', 'x', 1)");
        $this->pdo->exec("INSERT INTO vacations (id, user_id, start_date, end_date)
                          VALUES ('g-vac', 'g-owner', '2026-06-16', '2026-06-20')");
        $this->pdo->exec("INSERT INTO teams (id, name) VALUES ('g-team', 'Team A')");
    }

    /**
     * @dataProvider matrix
     */
    public function testGuardMatrix(string $method, string $path, ?array $body,
                                    ?string $as, int $expected): void
    {
        $this->seed();

        $res = $this->request($method, $path, $body, $as);

        $this->assertSame($expected, $res['status'], "$method $path som " . ($as ?? 'anonym'));
    }

    public static function matrix(): array
    {
        $dates = ['start' => '2026-06-16', 'end' => '2026-06-20'];
        $name  = ['name' => 'Nytt'];

        return [
            'bootstrap anonym'     => ['GET',    '/api/bootstrap',        null,   null,      401],
            'bootstrap ej ägare'   => ['GET',    '/api/bootstrap',        null,   'g-other', 200],
            'bootstrap admin'      => ['GET',    '/api/bootstrap',        null,   'g-admin', 200],
            'lösenord anonym'      => ['PUT',    '/api/session/password', [],     null,      401],
            'skapa semester anon'  => ['POST',   '/api/vacations',        $dates, null,      401],
            'skapa semester egen'  => ['POST',   '/api/vacations',        $dates, 'g-other', 201],
            'ändra annans'         => ['PUT',    '/api/vacations/g-vac',  $dates, 'g-other', 403],
            'ändra egen'           => ['PUT',    '/api/vacations/g-vac',  $dates, 'g-owner', 200],
            'ändra som admin'      => ['PUT',    '/api/vacations/g-vac',  $dates, 'g-admin', 200],
            'ta bort annans'       => ['DELETE', '/api/vacations/g-vac',  null,   'g-other', 403],
            'personer ej admin'    => ['PUT',    '/api/persons/g-other',  $name,  'g-other', 403],
            'personer anonym'      => ['POST',   '/api/persons',          $name,  null,      401],
            'grupper ej admin'     => ['PUT',    '/api/groups/g-team',    $name,  'g-owner', 403],
            'grupper anonym'       => ['DELETE', '/api/groups/g-team',    null,   null,      401],
        ];
    }
}
```

- [ ] **Step 2: Kör testerna och se att de failar**

Kör: `docker compose -f docker/docker-compose.yml exec -T php composer test -- --filter "VacationTest|GuardMatrixTest"`
Förväntat: FAIL — `501` för semesterraderna; matrisen failar på allt utom `401`.

- [ ] **Step 3: Implementera handlern**

`app/Handlers/VacationHandler.php`:

```php
<?php
namespace App\Handlers;

use App\Http;
use App\Uuid;
use PDO;

final class VacationHandler
{
    public function __construct(private PDO $pdo) {}

    public function create(?array $user, ?string $id = null): void
    {
        $body  = Http::body();
        $start = (string) ($body['start'] ?? '');
        $end   = (string) ($body['end'] ?? '');

        if (!$this->validRange($start, $end)) {
            Http::error('validation_failed', 'Slutdatum måste vara samma dag eller senare.', 422);
            return;
        }

        // Endast admin får skriva semester åt någon annan. För övriga kommer
        // personId från sessionen, aldrig från klienten.
        $personId = $user['isAdmin'] && isset($body['personId'])
            ? (string) $body['personId']
            : $user['id'];

        $newId = Uuid::v4();
        $stmt  = $this->pdo->prepare(
            'INSERT INTO vacations (id, user_id, start_date, end_date) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$newId, $personId, $start, $end]);

        Http::json(
            ['id' => $newId, 'personId' => $personId, 'start' => $start, 'end' => $end],
            201
        );
    }

    public function update(?array $user, ?string $id = null): void
    {
        $body  = Http::body();
        $start = (string) ($body['start'] ?? '');
        $end   = (string) ($body['end'] ?? '');

        if (!$this->validRange($start, $end)) {
            Http::error('validation_failed', 'Slutdatum måste vara samma dag eller senare.', 422);
            return;
        }

        // Ägarvillkoret upprepas som försvar på djupet; vakten har redan
        // svarat 404 eller 403 om raden saknas eller tillhör någon annan.
        $stmt = $this->pdo->prepare(
            'UPDATE vacations SET start_date = ?, end_date = ?
             WHERE id = ? AND (user_id = ? OR ? = 1)'
        );
        $stmt->execute([$start, $end, $id, $user['id'], (int) $user['isAdmin']]);

        Http::json(['id' => $id, 'start' => $start, 'end' => $end]);
    }

    public function destroy(?array $user, ?string $id = null): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM vacations WHERE id = ? AND (user_id = ? OR ? = 1)'
        );
        $stmt->execute([$id, $user['id'], (int) $user['isAdmin']]);

        Http::json(['ok' => true]);
    }

    private function validRange(string $start, string $end): bool
    {
        $isDate = static fn (string $d): bool =>
            (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;

        return $isDate($start) && $isDate($end) && $end >= $start;
    }
}
```

- [ ] **Step 4: Kör testerna och se att de passerar**

Kör: `docker compose -f docker/docker-compose.yml exec -T php composer test -- --filter "VacationTest|GuardMatrixTest"`
Förväntat: PASS. Matrisens `persons`- och `groups`-rader passerar redan här eftersom vakten svarar `403` före stubben.

- [ ] **Step 5: Commit**

```bash
git add app/Handlers/VacationHandler.php tests-php
git commit -m "feat: add vacation endpoints with ownership checks and guard matrix tests"
```

---

### Task 7: Personhandlern, partiell uppdatering och sista-admin-skyddet

**Files:**
- Modify: `app/Handlers/PersonHandler.php`
- Create: `app/Persons.php`, `db/create-admin.php`, `tests-php/PersonTest.php`
- Test: `tests-php/PersonTest.php`

**Interfaces:**
- Consumes: `App\Uuid`, `App\Http`.
- Produces:
  - `App\Persons::create(PDO $pdo, string $name, string $email, string $password, ?string $groupId, bool $isAdmin): array`
  - `App\Persons::wouldLeaveNoAdmin(PDO $pdo, string $userId): bool` — **måste anropas inuti en transaktion**
  - `POST`, `PUT`, `DELETE /api/persons[/{id}]`

- [ ] **Step 1: Skriv de failande testerna**

`tests-php/PersonTest.php`:

```php
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
```

- [ ] **Step 2: Kör testerna och se att de failar**

Kör: `docker compose -f docker/docker-compose.yml exec -T php composer test -- --filter PersonTest`
Förväntat: FAIL — `501`.

- [ ] **Step 3: Implementera den delade kontologiken**

`app/Persons.php`:

```php
<?php
namespace App;

use PDO;

final class Persons
{
    public static function create(
        PDO $pdo,
        string $name,
        string $email,
        string $password,
        ?string $groupId = null,
        bool $isAdmin = false
    ): array {
        $id = Uuid::v4();

        $stmt = $pdo->prepare(
            'INSERT INTO users (id, name, email, password_hash, team_id, is_admin)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $id,
            $name,
            strtolower(trim($email)),
            password_hash($password, PASSWORD_DEFAULT),
            $groupId,
            (int) $isAdmin,
        ]);

        return [
            'id'      => $id,
            'name'    => $name,
            'email'   => strtolower(trim($email)),
            'groupId' => $groupId,
            'isAdmin' => $isAdmin,
        ];
    }

    /**
     * Låser adminraderna innan de räknas. Utan FOR UPDATE ser två samtidiga
     * degraderingar i REPEATABLE READ båda samma snapshot, båda lyckas, och
     * systemet blir kvar utan administratör.
     *
     * Måste anropas inuti en transaktion, annars släpps låset direkt.
     */
    public static function wouldLeaveNoAdmin(PDO $pdo, string $userId): bool
    {
        $admins = $pdo->query('SELECT id FROM users WHERE is_admin = 1 FOR UPDATE')
                      ->fetchAll(PDO::FETCH_COLUMN);

        return count($admins) === 1 && $admins[0] === $userId;
    }
}
```

- [ ] **Step 4: Implementera handlern**

`app/Handlers/PersonHandler.php`:

```php
<?php
namespace App\Handlers;

use App\Http;
use App\HttpException;
use App\Persons;
use PDO;

final class PersonHandler
{
    public function __construct(private PDO $pdo) {}

    /**
     * MariaDB har inga nästlade transaktioner, och DbTestCase håller redan en
     * öppen. Äger vi inte transaktionen rör vi varken begin, commit eller
     * rollBack — en rollBack här hade rullat tillbaka testets egen uppsättning
     * och fått orelaterade tester att falla.
     */
    private function transactional(callable $fn): mixed
    {
        $owned = !$this->pdo->inTransaction();
        if ($owned) {
            $this->pdo->beginTransaction();
        }

        try {
            $result = $fn();
            if ($owned) {
                $this->pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            if ($owned) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function create(?array $user, ?string $id = null): void
    {
        $body     = Http::body();
        $name     = trim((string) ($body['name'] ?? ''));
        $email    = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            Http::error('validation_failed',
                'Namn, giltig e-post och lösenord på minst 8 tecken krävs.', 422);
            return;
        }

        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([strtolower($email)]);
        if ($stmt->fetchColumn() !== false) {
            Http::error('validation_failed', 'E-postadressen används redan.', 422);
            return;
        }

        Http::json(
            Persons::create($this->pdo, $name, $email, $password,
                $body['groupId'] ?? null, (bool) ($body['isAdmin'] ?? false)),
            201
        );
    }

    public function update(?array $user, ?string $id = null): void
    {
        $body = Http::body();

        $this->transactional(function () use ($body, $id): void {
        // array_key_exists, inte isset: isset() är false för null och skulle
        // tyst svälja varje försök att göra en person ogrupperad.
        $demoting = array_key_exists('isAdmin', $body) && !$body['isAdmin'];
        if ($demoting && Persons::wouldLeaveNoAdmin($this->pdo, $id)) {
            throw new HttpException('validation_failed',
                'Det måste finnas minst en administratör.', 422);
        }

        $columns = [
            'name'     => 'name',
            'email'    => 'email',
            'groupId'  => 'team_id',
            'isAdmin'  => 'is_admin',
        ];

        $sets   = [];
        $params = [];
        foreach ($columns as $field => $column) {
            if (!array_key_exists($field, $body)) {
                continue;
            }
            $sets[]   = "$column = ?";
            $params[] = match ($field) {
                'email'   => strtolower(trim((string) $body['email'])),
                'isAdmin' => (int) $body['isAdmin'],
                default   => $body[$field],
            };
        }

        if (array_key_exists('password', $body) && (string) $body['password'] !== '') {
            $sets[]   = 'password_hash = ?';
            $params[] = password_hash((string) $body['password'], PASSWORD_DEFAULT);
        }

        if ($sets !== []) {
            $params[] = $id;
            $stmt = $this->pdo->prepare(
                'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?'
            );
            $stmt->execute($params);
        }
        });

        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, team_id AS groupId, is_admin AS isAdmin
             FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if ($row === false) {
            throw new HttpException('not_found', 'Personen finns inte.', 404);
        }

        $row['isAdmin'] = (bool) $row['isAdmin'];
        Http::json($row);
    }

    public function destroy(?array $user, ?string $id = null): void
    {
        $this->transactional(function () use ($id): void {
            if (Persons::wouldLeaveNoAdmin($this->pdo, $id)) {
                throw new HttpException('validation_failed',
                    'Det måste finnas minst en administratör.', 422);
            }

            $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$id]);
        });

        Http::json(['ok' => true]);
    }
}
```

- [ ] **Step 5: Skriv samtidighetstestet för FOR UPDATE**

Det här testet kan inte ärva `DbTestCase`, eftersom det behöver **två egna
anslutningar** med var sin transaktion. Den delade transaktionen i basklassen
skulle annars göra båda skrivningarna till samma transaktion, och låset skulle
aldrig prövas. Testet städar därför upp efter sig själv.

`tests-php/LastAdminConcurrencyTest.php`:

```php
<?php
namespace Tests;

use App\Db;
use App\Persons;
use PDOException;
use PHPUnit\Framework\TestCase;

final class LastAdminConcurrencyTest extends TestCase
{
    private const DSN = [
        'host' => '127.0.0.1', 'port' => 3307, 'name' => 'vacation_test',
        'user' => 'root', 'pass' => 'root',
    ];

    public function testConcurrentDemotionsCannotBothSucceed(): void
    {
        $setup = Db::connect(self::DSN);
        $setup->exec("DELETE FROM users WHERE id IN ('conc1', 'conc2')");
        $setup->exec("INSERT INTO users (id, name, email, password_hash, is_admin) VALUES
            ('conc1', 'A', 'conc1@example.com', 'x', 1),
            ('conc2', 'B', 'conc2@example.com', 'x', 1)");

        $a = Db::connect(self::DSN);
        $b = Db::connect(self::DSN);
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
            $blocked = false;
            try {
                Persons::wouldLeaveNoAdmin($b, 'conc2');
            } catch (PDOException) {
                $blocked = true;
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
```

Kör: `docker compose -f docker/docker-compose.yml exec -T php composer test -- --filter LastAdminConcurrencyTest`
Förväntat: PASS. Testet failar om `FOR UPDATE` tas bort ur
`Persons::wouldLeaveNoAdmin()` — B ser då två administratörer och påståendet
faller.

- [ ] **Step 6: Skriv CLI-scriptet**

`db/create-admin.php`:

```php
<?php
declare(strict_types=1);

require __DIR__ . '/../app/Db.php';
require __DIR__ . '/../app/Uuid.php';
require __DIR__ . '/../app/Persons.php';

$config = require __DIR__ . '/../config.php';
$pdo    = App\Db::connect($config['db']);

fwrite(STDOUT, 'Namn: ');
$name = trim((string) fgets(STDIN));
fwrite(STDOUT, 'E-post: ');
$email = trim((string) fgets(STDIN));
fwrite(STDOUT, 'Lösenord: ');
$password = trim((string) fgets(STDIN));

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
    fwrite(STDERR, "Namn, giltig e-post och lösenord på minst 8 tecken krävs.\n");
    exit(1);
}

$person = App\Persons::create($pdo, $name, $email, $password, null, true);

fwrite(STDOUT, "Administratör skapad: {$person['email']} ({$person['id']})\n");
```

- [ ] **Step 7: Kör testerna och se att de passerar**

Kör: `docker compose -f docker/docker-compose.yml exec -T php composer test -- --filter "PersonTest|LastAdminConcurrencyTest"`
Förväntat: PASS — sju gröna tester plus samtidighetstestet.

- [ ] **Step 8: Commit**

```bash
git add app db/create-admin.php tests-php
git commit -m "feat: add person endpoints with partial updates and last-admin protection"
```

---

### Task 8: Grupphandlern

**Files:**
- Modify: `app/Handlers/GroupHandler.php`
- Create: `tests-php/GroupTest.php`
- Test: `tests-php/GroupTest.php`

**Interfaces:**
- Consumes: `App\Uuid`, `App\Http`.
- Produces: `POST`, `PUT`, `DELETE /api/groups[/{id}]`.

- [ ] **Step 1: Skriv de failande testerna**

`tests-php/GroupTest.php`:

```php
<?php
namespace Tests;

final class GroupTest extends ApiTestCase
{
    private function seedAdmin(): void
    {
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash, is_admin)
                          VALUES ('gr-admin', 'Admin', 'gr@example.com', 'x', 1)");
    }

    public function testCreateReturnsGroupWithId(): void
    {
        $this->seedAdmin();

        $res = $this->request('POST', '/api/groups', ['name' => 'Team A'], 'gr-admin');

        $this->assertSame(201, $res['status']);
        $this->assertSame('Team A', $res['json']['name']);
        $this->assertNotEmpty($res['json']['id']);
    }

    public function testDuplicateNameIsRejected(): void
    {
        $this->seedAdmin();
        $this->request('POST', '/api/groups', ['name' => 'Team A'], 'gr-admin');

        $res = $this->request('POST', '/api/groups', ['name' => 'Team A'], 'gr-admin');

        $this->assertSame(422, $res['status']);
    }

    public function testDeletingGroupUngroupsMembersInsteadOfDeletingThem(): void
    {
        $this->seedAdmin();
        $this->pdo->exec("INSERT INTO teams (id, name) VALUES ('gr1', 'Team A')");
        $this->pdo->exec("INSERT INTO users (id, name, email, password_hash, team_id)
                          VALUES ('gr-u1', 'Anna', 'anna@example.com', 'x', 'gr1')");

        $res = $this->request('DELETE', '/api/groups/gr1', null, 'gr-admin');

        $this->assertSame(200, $res['status']);
        $this->assertSame(1, (int) $this->pdo
            ->query("SELECT COUNT(*) FROM users WHERE id = 'gr-u1'")->fetchColumn());
        $this->assertNull($this->pdo
            ->query("SELECT team_id FROM users WHERE id = 'gr-u1'")->fetchColumn());
    }
}
```

- [ ] **Step 2: Kör testerna och se att de failar**

Kör: `docker compose -f docker/docker-compose.yml exec -T php composer test -- --filter GroupTest`
Förväntat: FAIL — `501`.

- [ ] **Step 3: Implementera handlern**

`app/Handlers/GroupHandler.php`:

```php
<?php
namespace App\Handlers;

use App\Http;
use App\Uuid;
use PDO;

final class GroupHandler
{
    public function __construct(private PDO $pdo) {}

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
        $stmt->execute([$newId, $name]);

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
        $stmt->execute([$name, $id]);

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
```

- [ ] **Step 4: Kör hela backend-sviten**

Kör: `docker compose -f docker/docker-compose.yml exec -T php composer test`
Förväntat: PASS — samtliga backend-tester gröna.

- [ ] **Step 5: Commit**

```bash
git add app/Handlers/GroupHandler.php tests-php/GroupTest.php
git commit -m "feat: add group endpoints"
```

---

### Task 9: apiClient i frontend

**Files:**
- Create: `src/lib/apiClient.js`, `tests/apiClient.test.js`
- Test: `tests/apiClient.test.js`

**Interfaces:**
- Consumes: ingenting från backend-tasken; kontraktet är HTTP.
- Produces:
  - `ApiError` med `status` och `code`
  - `createApiClient({ baseUrl = '/api', fetchImpl = fetch, onUnauthorized } = {})` → `{ get(path), post(path, body), put(path, body), del(path) }`

- [ ] **Step 1: Skriv det failande testet**

`tests/apiClient.test.js`:

```js
import { describe, it, expect, vi } from 'vitest';
import { createApiClient, ApiError } from '../src/lib/apiClient.js';

function response(status, body) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => body,
  };
}

describe('apiClient', () => {
  it('skickar JSON-header på skrivningar', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(response(200, { ok: true }));
    const api = createApiClient({ fetchImpl });

    await api.post('/vacations', { start: '2026-06-16' });

    const [url, init] = fetchImpl.mock.calls[0];
    expect(url).toBe('/api/vacations');
    expect(init.headers['Content-Type']).toBe('application/json');
    expect(init.credentials).toBe('same-origin');
  });

  it('anropar onUnauthorized vid 401', async () => {
    const onUnauthorized = vi.fn();
    const fetchImpl = vi.fn().mockResolvedValue(
      response(401, { error: { code: 'unauthenticated', message: 'Du måste logga in.' } })
    );
    const api = createApiClient({ fetchImpl, onUnauthorized });

    await expect(api.get('/bootstrap')).rejects.toBeInstanceOf(ApiError);
    expect(onUnauthorized).toHaveBeenCalledOnce();
  });

  it('bär med serverns felmeddelande vid 403 och 422', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(
      response(403, { error: { code: 'forbidden', message: 'Du får bara ändra din egen semester.' } })
    );
    const api = createApiClient({ fetchImpl });

    await expect(api.put('/vacations/1', {})).rejects.toMatchObject({
      status: 403,
      code: 'forbidden',
      message: 'Du får bara ändra din egen semester.',
    });
  });

  it('ger ett begripligt fel när nätverket ligger nere', async () => {
    const fetchImpl = vi.fn().mockRejectedValue(new TypeError('Failed to fetch'));
    const api = createApiClient({ fetchImpl });

    await expect(api.get('/bootstrap')).rejects.toMatchObject({
      status: 0,
      code: 'network_error',
    });
  });
});
```

- [ ] **Step 2: Kör testet och se att det failar**

Kör: `npx vitest run tests/apiClient.test.js`
Förväntat: FAIL — modulen finns inte.

- [ ] **Step 3: Implementera klienten**

`src/lib/apiClient.js`:

```js
export class ApiError extends Error {
  constructor(message, status, code) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
  }
}

export function createApiClient({ baseUrl = '/api', fetchImpl = fetch, onUnauthorized } = {}) {
  async function send(method, path, body) {
    let res;
    try {
      res = await fetchImpl(`${baseUrl}${path}`, {
        method,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
      });
    } catch {
      throw new ApiError('Kunde inte nå servern. Kontrollera din anslutning.', 0, 'network_error');
    }

    const payload = await res.json().catch(() => ({}));

    if (res.ok) return payload;

    if (res.status === 401 && onUnauthorized) onUnauthorized();

    const error = payload.error ?? {};
    throw new ApiError(error.message ?? 'Ett fel uppstod.', res.status, error.code ?? 'unknown');
  }

  return {
    get: (path) => send('GET', path),
    post: (path, body) => send('POST', path, body ?? {}),
    put: (path, body) => send('PUT', path, body ?? {}),
    del: (path) => send('DELETE', path),
  };
}
```

- [ ] **Step 4: Kör testet och se att det passerar**

Kör: `npx vitest run tests/apiClient.test.js`
Förväntat: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/lib/apiClient.js tests/apiClient.test.js
git commit -m "feat: add API client with error mapping"
```

---

### Task 10: dataStore mot API:et, GitHub-klienterna bort

**Files:**
- Modify: `src/lib/dataStore.js`, `tests/dataStore.test.js`
- Delete: `src/lib/githubClient.js`, `src/lib/localClient.js`, `tests/githubClient.test.js`, `tests/localClient.test.js`, `data/registry.json`, `data/vacations.json`

**Interfaces:**
- Consumes: `createApiClient` från Task 9.
- Produces: `createDataStore({ api })` med `bootstrap(from, to)`, `addGroup`, `renameGroup`, `removeGroup`, `addPerson`, `updatePerson`, `setPersonGroup`, `removePerson`, `addVacation`, `updateVacation`, `removeVacation`. Varje skrivmetod returnerar serverns svar, inte hela datasetet.

- [ ] **Step 1: Skriv det failande testet**

`tests/dataStore.test.js` (ersätter filens hela innehåll):

```js
import { describe, it, expect, vi } from 'vitest';
import { createDataStore } from '../src/lib/dataStore.js';

function fakeApi(overrides = {}) {
  return {
    get: vi.fn().mockResolvedValue({}),
    post: vi.fn().mockResolvedValue({}),
    put: vi.fn().mockResolvedValue({}),
    del: vi.fn().mockResolvedValue({ ok: true }),
    ...overrides,
  };
}

describe('dataStore', () => {
  it('hämtar startdata med datumintervall', async () => {
    const api = fakeApi({
      get: vi.fn().mockResolvedValue({ me: { id: 'u1' }, groups: [], persons: [], vacations: [] }),
    });
    const store = createDataStore({ api });

    const data = await store.bootstrap('2026-06-01', '2026-06-30');

    expect(api.get).toHaveBeenCalledWith('/bootstrap?from=2026-06-01&to=2026-06-30');
    expect(data.me.id).toBe('u1');
  });

  it('skickar bara groupId vid gruppbyte', async () => {
    const api = fakeApi();
    const store = createDataStore({ api });

    await store.setPersonGroup('p1', 't1');

    expect(api.put).toHaveBeenCalledWith('/persons/p1', { groupId: 't1' });
  });

  it('skickar groupId null när personen ska bli ogrupperad', async () => {
    const api = fakeApi();
    const store = createDataStore({ api });

    await store.setPersonGroup('p1', null);

    expect(api.put).toHaveBeenCalledWith('/persons/p1', { groupId: null });
  });

  it('returnerar den skapade semesterraden i stället för hela datasetet', async () => {
    const created = { id: 'v1', personId: 'p1', start: '2026-06-16', end: '2026-06-20' };
    const api = fakeApi({ post: vi.fn().mockResolvedValue(created) });
    const store = createDataStore({ api });

    const row = await store.addVacation('p1', '2026-06-16', '2026-06-20');

    expect(api.post).toHaveBeenCalledWith('/vacations',
      { personId: 'p1', start: '2026-06-16', end: '2026-06-20' });
    expect(row).toEqual(created);
  });
});
```

- [ ] **Step 2: Kör testet och se att det failar**

Kör: `npx vitest run tests/dataStore.test.js`
Förväntat: FAIL — `createDataStore` tar fortfarande `{ client, registryPath, vacationsPath }`.

- [ ] **Step 3: Skriv om dataStore**

`src/lib/dataStore.js` (ersätter filens hela innehåll):

```js
export function createDataStore({ api }) {
  return {
    bootstrap(from, to) {
      return api.get(`/bootstrap?from=${from}&to=${to}`);
    },

    addGroup(name) {
      return api.post('/groups', { name });
    },
    renameGroup(id, name) {
      return api.put(`/groups/${id}`, { name });
    },
    removeGroup(id) {
      return api.del(`/groups/${id}`);
    },

    addPerson({ name, email, password, groupId = null, isAdmin = false }) {
      return api.post('/persons', { name, email, password, groupId, isAdmin });
    },
    // Skickar bara de fält som faktiskt ändras. Servern lämnar övriga orörda,
    // så ett gruppbyte skriver inte över namn eller e-post.
    updatePerson(id, patch) {
      return api.put(`/persons/${id}`, patch);
    },
    setPersonGroup(id, groupId) {
      return api.put(`/persons/${id}`, { groupId });
    },
    removePerson(id) {
      return api.del(`/persons/${id}`);
    },

    addVacation(personId, start, end) {
      return api.post('/vacations', { personId, start, end });
    },
    updateVacation(id, start, end) {
      return api.put(`/vacations/${id}`, { start, end });
    },
    removeVacation(id) {
      return api.del(`/vacations/${id}`);
    },
  };
}
```

- [ ] **Step 4: Radera GitHub- och localStorage-lagren**

```bash
git rm src/lib/githubClient.js src/lib/localClient.js
git rm tests/githubClient.test.js tests/localClient.test.js
git rm data/registry.json data/vacations.json
```

- [ ] **Step 5: Kör testet och se att det passerar**

Kör: `npx vitest run tests/dataStore.test.js`
Förväntat: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A src/lib tests data
git commit -m "refactor: replace the GitHub data layer with an HTTP data store"
```

---

### Task 11: auth, identitet och inloggningsvyn

**Files:**
- Modify: `src/lib/auth.js`, `tests/auth.test.js`, `src/components/LoginView.vue`, `tests/LoginView.test.js`
- Delete: `src/lib/identity.js`, `tests/identity.test.js`, `src/components/PersonSelectView.vue`, `tests/PersonSelectView.test.js`

**Interfaces:**
- Consumes: `createApiClient` från Task 9.
- Produces:
  - `login(api, email, password): Promise<user>`
  - `logout(api): Promise<void>`
  - `currentUser(api): Promise<user|null>` — `null` vid `401`
  - `changePassword(api, currentPassword, newPassword): Promise<void>`
  - `LoginView` emittar `submit` med `{ email, password }`

- [ ] **Step 1: Skriv de failande testerna**

`tests/auth.test.js` (ersätter filens hela innehåll):

```js
import { describe, it, expect, vi } from 'vitest';
import { login, logout, currentUser } from '../src/lib/auth.js';
import { ApiError } from '../src/lib/apiClient.js';

describe('auth', () => {
  it('loggar in mot /session och returnerar användaren', async () => {
    const api = { post: vi.fn().mockResolvedValue({ id: 'u1', isAdmin: false }) };

    const user = await login(api, 'anna@example.com', 'hemligt123');

    expect(api.post).toHaveBeenCalledWith('/session',
      { email: 'anna@example.com', password: 'hemligt123' });
    expect(user.id).toBe('u1');
  });

  it('returnerar null från currentUser när sessionen saknas', async () => {
    const api = { get: vi.fn().mockRejectedValue(new ApiError('nej', 401, 'unauthenticated')) };

    expect(await currentUser(api)).toBeNull();
  });

  it('låter andra fel bubbla upp från currentUser', async () => {
    const api = { get: vi.fn().mockRejectedValue(new ApiError('trasig', 500, 'server_error')) };

    await expect(currentUser(api)).rejects.toMatchObject({ status: 500 });
  });

  it('skriver inte längre någon flagga i localStorage', async () => {
    const api = { post: vi.fn().mockResolvedValue({ id: 'u1' }) };
    localStorage.clear();

    await login(api, 'anna@example.com', 'hemligt123');

    expect(localStorage.getItem('authenticated')).toBeNull();
  });

  it('loggar ut mot /session', async () => {
    const api = { del: vi.fn().mockResolvedValue({ ok: true }) };

    await logout(api);

    expect(api.del).toHaveBeenCalledWith('/session');
  });
});
```

`tests/LoginView.test.js` — **ersätt filens hela innehåll.** Befintliga tester
väntar sig att `submit` bär en lösenordssträng och att `error` är en boolean;
båda antagandena faller nu.

```js
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import LoginView from '../src/components/LoginView.vue';

describe('LoginView', () => {
  it('emittar submit med e-post och lösenord', async () => {
    const wrapper = mount(LoginView);

    await wrapper.find('[data-testid="email"]').setValue('anna@example.com');
    await wrapper.find('[data-testid="password"]').setValue('hemligt123');
    await wrapper.find('form').trigger('submit.prevent');

    expect(wrapper.emitted('submit')[0][0]).toEqual({
      email: 'anna@example.com',
      password: 'hemligt123',
    });
  });

  it('visar serverns felmeddelande i stället för en fast text', () => {
    const wrapper = mount(LoginView, {
      props: { error: 'För många försök. Vänta en stund.' },
    });

    expect(wrapper.text()).toContain('För många försök. Vänta en stund.');
  });
});
```

- [ ] **Step 2: Kör testerna och se att de failar**

Kör: `npx vitest run tests/auth.test.js tests/LoginView.test.js`
Förväntat: FAIL — `login` har fortfarande signaturen `(password, expected, storage)`.

- [ ] **Step 3: Skriv om auth**

`src/lib/auth.js` (ersätter filens hela innehåll):

```js
import { ApiError } from './apiClient.js';

export function login(api, email, password) {
  return api.post('/session', { email, password });
}

export function logout(api) {
  return api.del('/session');
}

// Sessionen är identiteten. Ingen flagga i localStorage, inget personId.
export async function currentUser(api) {
  try {
    return await api.get('/session');
  } catch (e) {
    if (e instanceof ApiError && e.status === 401) return null;
    throw e;
  }
}

export function changePassword(api, currentPassword, newPassword) {
  return api.put('/session/password', { currentPassword, newPassword });
}
```

- [ ] **Step 4: Skriv om LoginView**

`src/components/LoginView.vue` (ersätter filens hela innehåll):

```vue
<template>
  <div class="min-h-screen flex items-center justify-center bg-base-200">
    <form class="card bg-base-100 shadow-md p-6 w-80" @submit.prevent="onSubmit">
      <h1 class="text-xl font-bold mb-4">Logga in</h1>
      <input
        data-testid="email"
        type="email"
        class="input input-bordered w-full mb-2"
        placeholder="E-post"
        v-model="email"
      />
      <input
        data-testid="password"
        type="password"
        class="input input-bordered w-full mb-2"
        placeholder="Lösenord"
        v-model="password"
      />
      <p v-if="error" class="text-error text-sm mb-2">{{ error }}</p>
      <button type="submit" class="btn btn-primary w-full">Logga in</button>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue';

// error är nu en sträng från servern, inte en boolean. Meddelandet skiljer på
// fel lösenord, spärrat konto och nätverksfel — den gamla fasta texten
// "Fel lösenord" hade varit direkt missvisande vid 429.
defineProps({ error: { type: String, default: null } });
const emit = defineEmits(['submit']);

const email = ref('');
const password = ref('');

function onSubmit() {
  emit('submit', { email: email.value, password: password.value });
}
</script>
```

- [ ] **Step 5: Radera identitetslagret**

```bash
git rm src/lib/identity.js tests/identity.test.js
git rm src/components/PersonSelectView.vue tests/PersonSelectView.test.js
```

- [ ] **Step 6: Kör testerna och se att de passerar**

Kör: `npx vitest run tests/auth.test.js tests/LoginView.test.js`
Förväntat: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A src tests
git commit -m "refactor: move authentication to the server session and drop local identity"
```

---

### Task 12: useStore med lokal uppdatering

**Files:**
- Modify: `src/composables/useStore.js`, `tests/useStore.test.js`

**Interfaces:**
- Consumes: `createDataStore` från Task 10.
- Produces: `createStore(dataStore)` med `groups`, `persons`, `vacations`, `me`, `loading`, `error`, `refresh(from, to)` och skrivmetoderna. Skrivningar uppdaterar de lokala listorna från serverns svar; full omladdning sker bara i `refresh`.

- [ ] **Step 1: Skriv det failande testet**

`tests/useStore.test.js` — **ersätt filens hela innehåll.** De befintliga
testerna mockar `loadRegistry` och `loadVacations`, som inte längre finns.

```js
import { describe, it, expect, vi } from 'vitest';
import { createStore } from '../src/composables/useStore.js';

function fakeDataStore(overrides = {}) {
  return {
    bootstrap: vi.fn().mockResolvedValue({
      me: { id: 'p1', isAdmin: false },
      groups: [{ id: 'g1', name: 'A' }],
      persons: [{ id: 'p1', name: 'Anna', groupId: 'g1' }],
      vacations: [],
    }),
    ...overrides,
  };
}

describe('useStore', () => {
  it('refresh fyller state från bootstrap', async () => {
    const store = createStore(fakeDataStore());

    await store.refresh('2026-06-01', '2026-06-30');

    expect(store.groups.value).toHaveLength(1);
    expect(store.persons.value).toHaveLength(1);
    expect(store.me.value.id).toBe('p1');
    expect(store.error.value).toBe(null);
  });

  it('sätter error vid läsfel', async () => {
    const ds = fakeDataStore({
      bootstrap: vi.fn().mockRejectedValue(new Error('nätverk')),
    });
    const store = createStore(ds);

    await store.refresh('2026-06-01', '2026-06-30');

    expect(store.error.value).toBeTruthy();
  });

  it('lägger till den skapade raden lokalt utan att ladda om allt', async () => {
  const dataStore = fakeDataStore({
    addVacation: vi.fn().mockResolvedValue(
      { id: 'v1', personId: 'p1', start: '2026-06-16', end: '2026-06-20' }
    ),
  });
  const store = createStore(dataStore);
  await store.refresh('2026-06-01', '2026-06-30');

  await store.addVacation('p1', '2026-06-16', '2026-06-20');

  expect(store.vacations.value).toHaveLength(1);
  expect(dataStore.bootstrap).toHaveBeenCalledTimes(1);
});

it('tar bort raden lokalt vid radering', async () => {
  const dataStore = fakeDataStore({
    bootstrap: vi.fn().mockResolvedValue({
      me: { id: 'p1' }, groups: [], persons: [],
      vacations: [{ id: 'v1', personId: 'p1', start: '2026-06-16', end: '2026-06-20' }],
    }),
    removeVacation: vi.fn().mockResolvedValue({ ok: true }),
  });
  const store = createStore(dataStore);
  await store.refresh('2026-06-01', '2026-06-30');

  await store.removeVacation('v1');

  expect(store.vacations.value).toHaveLength(0);
  expect(dataStore.bootstrap).toHaveBeenCalledTimes(1);
  });
});
```

- [ ] **Step 2: Kör testet och se att det failar**

Kör: `npx vitest run tests/useStore.test.js`
Förväntat: FAIL — `dataStore.loadRegistry is not a function`.

- [ ] **Step 3: Skriv om storen**

`src/composables/useStore.js` (ersätter filens hela innehåll):

```js
import { ref } from 'vue';

export function createStore(dataStore) {
  const groups = ref([]);
  const persons = ref([]);
  const vacations = ref([]);
  const me = ref(null);
  const loading = ref(false);
  const error = ref(null);
  let range = { from: null, to: null };

  async function refresh(from = range.from, to = range.to) {
    range = { from, to };
    loading.value = true;
    error.value = null;
    try {
      const data = await dataStore.bootstrap(from, to);
      me.value = data.me;
      groups.value = data.groups;
      persons.value = data.persons;
      vacations.value = data.vacations;
    } catch (e) {
      error.value = e.message || 'Ett fel uppstod vid laddning.';
    } finally {
      loading.value = false;
    }
  }

  // Skrivningar uppdaterar listorna lokalt från serverns svar. Full omladdning
  // sker bara vid appstart och när datumintervallet ändras — annars laddades
  // hela datasetet om varje gång ett datum flyttades.
  function mutate(fn) {
    return async (...args) => {
      try {
        return await fn(...args);
      } catch (e) {
        error.value = e.message || 'Ett fel uppstod vid sparande.';
        throw e;
      }
    };
  }

  function replace(list, row) {
    const i = list.value.findIndex((x) => x.id === row.id);
    if (i === -1) list.value = [...list.value, row];
    else list.value = list.value.map((x) => (x.id === row.id ? { ...x, ...row } : x));
  }

  return {
    groups, persons, vacations, me, loading, error, refresh,

    addGroup: mutate(async (name) => replace(groups, await dataStore.addGroup(name))),
    renameGroup: mutate(async (id, name) => replace(groups, await dataStore.renameGroup(id, name))),
    removeGroup: mutate(async (id) => {
      await dataStore.removeGroup(id);
      groups.value = groups.value.filter((g) => g.id !== id);
      persons.value = persons.value.map((p) => (p.groupId === id ? { ...p, groupId: null } : p));
    }),

    addPerson: mutate(async (payload) => replace(persons, await dataStore.addPerson(payload))),
    updatePerson: mutate(async (id, patch) =>
      replace(persons, await dataStore.updatePerson(id, patch))),
    setPersonGroup: mutate(async (id, groupId) =>
      replace(persons, await dataStore.setPersonGroup(id, groupId))),
    removePerson: mutate(async (id) => {
      await dataStore.removePerson(id);
      persons.value = persons.value.filter((p) => p.id !== id);
      vacations.value = vacations.value.filter((v) => v.personId !== id);
    }),

    addVacation: mutate(async (personId, start, end) =>
      replace(vacations, await dataStore.addVacation(personId, start, end))),
    updateVacation: mutate(async (id, start, end) =>
      replace(vacations, await dataStore.updateVacation(id, start, end))),
    removeVacation: mutate(async (id) => {
      await dataStore.removeVacation(id);
      vacations.value = vacations.value.filter((v) => v.id !== id);
    }),
  };
}
```

- [ ] **Step 4: Kör testet och se att det passerar**

Kör: `npx vitest run tests/useStore.test.js`
Förväntat: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/composables/useStore.js tests/useStore.test.js
git commit -m "perf: update store state from responses instead of reloading everything"
```

---

### Task 13: AdminPanel, lösenordsbyte och App-wiring

**Files:**
- Modify: `src/components/AdminPanel.vue`, `tests/AdminPanel.test.js`, `src/App.vue`, `src/composables/useDefaultRange.js`, `tests/useDefaultRange.test.js`
- Create: `src/components/ChangePasswordView.vue`, `tests/ChangePasswordView.test.js`

**Interfaces:**
- Consumes: `createStore`, `login`, `logout`, `currentUser`, `changePassword`.
- Produces: `AdminPanel` emittar `add-person` med `{ name, email, password, groupId, isAdmin }`. `ChangePasswordView` emittar `submit` med `{ currentPassword, newPassword }`.

- [ ] **Step 1: Skriv de failande testerna**

`tests/AdminPanel.test.js` — **ersätt filens hela innehåll.** Det befintliga
testet väntar sig `add-person` med `{ name, groupId }`, vilket inte längre är
nyttolasten.

```js
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import AdminPanel from '../src/components/AdminPanel.vue';

const groups = [{ id: 'g1', name: 'Team A' }];
const persons = [{ id: 'p1', name: 'Anna', groupId: 'g1' }];

describe('AdminPanel', () => {
  it('emittar add-group med namnet', async () => {
    const wrapper = mount(AdminPanel, { props: { groups, persons } });

    await wrapper.find('[data-testid="new-group"]').setValue('Team B');
    await wrapper.find('[data-testid="add-group"]').trigger('click');

    expect(wrapper.emitted('add-group')[0][0]).toBe('Team B');
  });

  it('emittar set-person-group vid gruppbyte', async () => {
    const wrapper = mount(AdminPanel, { props: { groups, persons } });

    await wrapper.find('[data-testid="person-group-p1"]').setValue('g1');

    expect(wrapper.emitted('set-person-group')[0][0]).toEqual({ id: 'p1', groupId: 'g1' });
  });

  it('emittar add-person med e-post, lösenord och adminflagga', async () => {
    const wrapper = mount(AdminPanel, { props: { groups, persons } });

    await wrapper.find('[data-testid="new-person"]').setValue('Bo');
    await wrapper.find('[data-testid="new-person-email"]').setValue('bo@example.com');
    await wrapper.find('[data-testid="new-person-password"]').setValue('hemligt123');
    await wrapper.find('[data-testid="new-person-admin"]').setValue(true);
    await wrapper.find('[data-testid="add-person"]').trigger('click');

    expect(wrapper.emitted('add-person')[0][0]).toEqual({
      name: 'Bo',
      email: 'bo@example.com',
      password: 'hemligt123',
      groupId: null,
      isAdmin: true,
    });
  });
});
```

**Not:** `data-testid="add-group"`, `add-person` och `person-group-<id>` måste
finnas i `AdminPanel.vue`. Saknas de, lägg till dem i steg 3 — testerna ovan är
skrivna mot dem.

`tests/ChangePasswordView.test.js`:

```js
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ChangePasswordView from '../src/components/ChangePasswordView.vue';

describe('ChangePasswordView', () => {
  it('kräver att bekräftelsen matchar', async () => {
    const wrapper = mount(ChangePasswordView);

    await wrapper.find('[data-testid="current"]').setValue('gammalt123');
    await wrapper.find('[data-testid="new"]').setValue('nytt123456');
    await wrapper.find('[data-testid="confirm"]').setValue('felstavat');
    await wrapper.find('form').trigger('submit.prevent');

    expect(wrapper.emitted('submit')).toBeUndefined();
    expect(wrapper.text()).toContain('stämmer inte överens');
  });

  it('emittar submit när fälten stämmer', async () => {
    const wrapper = mount(ChangePasswordView);

    await wrapper.find('[data-testid="current"]').setValue('gammalt123');
    await wrapper.find('[data-testid="new"]').setValue('nytt123456');
    await wrapper.find('[data-testid="confirm"]').setValue('nytt123456');
    await wrapper.find('form').trigger('submit.prevent');

    expect(wrapper.emitted('submit')[0][0]).toEqual({
      currentPassword: 'gammalt123',
      newPassword: 'nytt123456',
    });
  });
});
```

- [ ] **Step 2: Kör testerna och se att de failar**

Kör: `npx vitest run tests/AdminPanel.test.js tests/ChangePasswordView.test.js`
Förväntat: FAIL — fälten och komponenten saknas.

- [ ] **Step 3: Bygg ut AdminPanel**

Lägg till tre fält i formuläret för ny person i `src/components/AdminPanel.vue`:

```vue
<input data-testid="new-person-email" type="email" class="input input-bordered input-sm"
       placeholder="E-post" v-model="newPersonEmail" />
<input data-testid="new-person-password" type="password" class="input input-bordered input-sm"
       placeholder="Lösenord" v-model="newPersonPassword" />
<label class="label cursor-pointer gap-2">
  <span class="label-text">Administratör</span>
  <input data-testid="new-person-admin" type="checkbox" class="checkbox checkbox-sm"
         v-model="newPersonAdmin" />
</label>
```

Ändra emit-anropet till:

```js
emit('add-person', {
  name: newPerson.value.trim(),
  email: newPersonEmail.value.trim(),
  password: newPersonPassword.value,
  groupId: newPersonGroup.value || null,
  isAdmin: newPersonAdmin.value,
});
```

- [ ] **Step 4: Skapa ChangePasswordView**

`src/components/ChangePasswordView.vue`:

```vue
<template>
  <form class="flex flex-col gap-2 max-w-sm" @submit.prevent="onSubmit">
    <input data-testid="current" type="password" class="input input-bordered"
           placeholder="Nuvarande lösenord" v-model="current" />
    <input data-testid="new" type="password" class="input input-bordered"
           placeholder="Nytt lösenord" v-model="next" />
    <input data-testid="confirm" type="password" class="input input-bordered"
           placeholder="Bekräfta nytt lösenord" v-model="confirm" />
    <p v-if="mismatch" class="text-error text-sm">Lösenorden stämmer inte överens.</p>
    <button class="btn btn-primary btn-sm" type="submit">Byt lösenord</button>
  </form>
</template>

<script setup>
import { ref } from 'vue';

const emit = defineEmits(['submit']);
const current = ref('');
const next = ref('');
const confirm = ref('');
const mismatch = ref(false);

function onSubmit() {
  mismatch.value = next.value !== confirm.value;
  if (mismatch.value) return;
  emit('submit', { currentPassword: current.value, newPassword: next.value });
}
</script>
```

- [ ] **Step 5: Lägg till månad-till-datum-konvertering**

`defaultRange(today)` returnerar **månader** (`{ from: '2026-09', to: '2026-11' }`),
men `/api/bootstrap` vill ha datum. Konverteringen saknas och måste skrivas.

Lägg till i `src/composables/useDefaultRange.js`:

```js
// defaultRange ger månader; API:et vill ha datum. Sista dagen i to-månaden
// räknas ut med dag 0 i nästa månad, vilket hanterar både 28, 29, 30 och 31.
export function monthRangeToDates({ from, to }) {
  const [year, month] = to.split('-').map(Number);
  const lastDay = new Date(Date.UTC(year, month, 0)).getUTCDate();

  return { from: `${from}-01`, to: `${to}-${String(lastDay).padStart(2, '0')}` };
}
```

Lägg till i `tests/useDefaultRange.test.js` (befintliga tester rörs inte):

```js
import { monthRangeToDates } from '../src/composables/useDefaultRange.js';

it('konverterar månadsintervall till datum', () => {
  expect(monthRangeToDates({ from: '2026-09', to: '2026-11' }))
    .toEqual({ from: '2026-09-01', to: '2026-11-30' });
});

it('hanterar februari under skottår', () => {
  expect(monthRangeToDates({ from: '2024-02', to: '2024-02' }))
    .toEqual({ from: '2024-02-01', to: '2024-02-29' });
});
```

Kör: `npx vitest run tests/useDefaultRange.test.js`
Förväntat: PASS.

**Konsekvens för räkningen i spec §10:** `useDefaultRange.test.js` var listad
som orörd. Den blir nu åtta orörda testfiler i stället för nio, plus en utökad.
De befintliga testerna i filen ändras inte.

- [ ] **Step 6: Ändra App.vue**

`App.vue` innehåller `range`, `groupFilterId`, `modalOpen`, `editing`,
`openNewVacation`, `openEditVacation`, `onSaveVacation` och `onDeleteVacation`.
**Inget av det får raderas** — det är kalendervyn. Gör följande ändringar i
befintlig fil:

**Importer.** Ta bort `createGithubClient`, `createLocalClient`,
`isAuthenticated`, `setPersonId`, `resolveIdentity` och `PersonSelectView`.
Lägg till:

```js
import { ref, computed, onMounted, watch } from 'vue';
import { createApiClient } from './lib/apiClient.js';
import { createStore } from './composables/useStore.js';
import { defaultRange, monthRangeToDates } from './composables/useDefaultRange.js';
import { login, logout, currentUser, changePassword } from './lib/auth.js';
import ChangePasswordView from './components/ChangePasswordView.vue';
```

**Klient och store.** Ersätt hela `client`/`dataStore`-blocket med:

```js
// onUnauthorized fångar en session som gått ut mitt i arbetet: appen faller
// tillbaka till inloggningsvyn i stället för att visa ett tomt rutnät.
const api = createApiClient({
  baseUrl: config.apiBaseUrl,
  onUnauthorized: () => { user.value = null; },
});
const store = createStore(createDataStore({ api }));
```

**Identitet.** Ersätt `authed`, `loginError` och `myPersonId` med:

```js
const user = ref(null);
const loginError = ref(null);          // sträng, inte boolean
const isAdmin = computed(() => store.me.value?.isAdmin === true);
const myName = computed(() => store.me.value?.name ?? '');
```

Ta bort `onSelectPerson`, `onCreatePerson` och `afterAuth`. Behåll
`onRemovePerson` men ta bort raden som anropar `resolveIdentity`.

**Laddning och intervallbyte.** `range` håller månader, så varje anrop går
genom konverteringen. Utan bevakningen laddas aldrig nya månader in:

```js
function reload() {
  const { from, to } = monthRangeToDates(range.value);
  return store.refresh(from, to);
}

onMounted(async () => {
  user.value = await currentUser(api);
  if (user.value) await reload();
});

watch(range, reload);

async function onLogin({ email, password }) {
  loginError.value = null;
  try {
    user.value = await login(api, email, password);
    await reload();
  } catch (e) {
    loginError.value = e.message;
  }
}

async function onLogout() {
  await logout(api);
  user.value = null;
}

function onChangePassword({ currentPassword, newPassword }) {
  return changePassword(api, currentPassword, newPassword);
}
```

**Semestersparande.** `onSaveVacation` använde `myPersonId`. Byt den raden till
`store.me.value.id`:

```js
else await store.addVacation(store.me.value.id, start, end);
```

**Template.** Ta bort hela `PersonSelectView`-blocket och ändra villkoren:
`LoginView` renderas på `v-if="!user"`, huvudvyn på `v-else`. `AdminPanel` får
`v-if="isAdmin"`, och dess `@add-person` blir `store.addPerson` (en enda
objekt-parameter nu, inte två). Lägg till `<ChangePasswordView
@submit="onChangePassword" />` och en utloggningsknapp som anropar `onLogout`.
`store.refresh()` i felrutans "Försök igen"-knapp byts mot `reload`.

- [ ] **Step 7: Kör hela frontend-sviten**

Kör: `npm test -- --run`
Förväntat: PASS — samtliga testfiler gröna.

- [ ] **Step 8: Commit**

```bash
git add src tests
git commit -m "feat: extend the admin panel and add self-service password change"
```

---

### Task 14: Bygge, proxy och deploy

**Files:**
- Modify: `vite.config.js`, `src/config.js`, `package.json`, `README.md`, `CLAUDE.md`
- Create: `scripts/deploy.sh`
- Delete: `.env` och `.env.example` om de finns; ingen `VITE_`-variabel får finnas kvar i `src/`

**Interfaces:**
- Consumes: allt ovan.
- Produces: `npm run dev` mot Docker-backend, `npm run build` med `base: '/'`, `npm run deploy` via `scripts/deploy.sh`.

- [ ] **Step 1: Uppdatera Vite-konfigurationen**

`vite.config.js`:

```js
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  // Appen får en egen portal med egen document root på Web Station,
  // så den ligger inte längre i en underkatalog som på GitHub Pages.
  base: '/',
  plugins: [vue()],
  server: {
    // Samma origin ur browserns perspektiv — ingen CORS behövs,
    // varken här eller i produktion.
    proxy: { '/api': 'http://localhost:8080' },
  },
  test: {
    environment: 'jsdom',
    globals: true,
  },
});
```

- [ ] **Step 2: Krymp config.js**

`src/config.js` (ersätter filens hela innehåll):

```js
// Frontend innehåller inga hemligheter längre. Allt känsligt ligger i
// config.php på servern.
export default {
  apiBaseUrl: '/api',
};
```

- [ ] **Step 3: Skriv deploy-scriptet**

`scripts/deploy.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

NAS="${NAS:-rickard@synstation}"
ROOT="/volume1/web/vacation"

if ! command -v rsync >/dev/null 2>&1; then
  echo "rsync saknas. Kör från WSL eller Git Bash med rsync installerat," >&2
  echo "eller kopiera manuellt med scp -r (utan --delete)." >&2
  exit 1
fi

npm run build

# --exclude=api/ är obligatoriskt: dist/ innehåller inte api/, så ett
# --delete utan undantaget raderar front controllern och tar ner API:et.
rsync -az --delete --exclude=api/ dist/ "$NAS:$ROOT/public/"
rsync -az --delete app/ "$NAS:$ROOT/app/"

echo "Klart. db/ och config.php deployas separat vid installationen."
```

Gör den körbar: `chmod +x scripts/deploy.sh`.

- [ ] **Step 4: Uppdatera package.json**

```bash
npm uninstall gh-pages
```

Ändra `scripts.deploy` till `"deploy": "bash scripts/deploy.sh"` och uppdatera
`description` till att beskriva PHP- och MariaDB-backendet i stället för
GitHub Pages.

- [ ] **Step 5: Uppdatera dokumentationen**

Uppdatera `README.md` och `CLAUDE.md` så att de beskriver den nya arkitekturen:
PHP-backend, MariaDB, Docker för utveckling, `create-admin.php` för första
kontot. Ta bort allt om GitHub API, PAT och `gh-pages`.

- [ ] **Step 6: Kör hela sviten och bygget**

Kör: `npm test -- --run && npm run build && docker compose -f docker/docker-compose.yml exec -T php composer test`
Förväntat: PASS på alla tre.

Kontrollera samtidigt att de åtta helt orörda testfilerna fortfarande är gröna
utan att ha ändrats: `dates`, `grouping`, `validation`, `vacationLookup`,
`CalendarGrid`, `CalendarControls`, `VacationModal` och `smoke`.
`useDefaultRange.test.js` har fått ett tillägg i Task 13 men inga ändrade
tester. `git diff --stat main -- tests/` ska inte visa de åtta. Det är
kvittot på att datalagret verkligen var isolerat — behövde någon av dem ändras
har en gräns läckt, och det bör rapporteras i stället för tystas ner.

Verifiera också att inga hemligheter finns kvar i bygget:

```bash
grep -rn "VITE_" src/ || echo "Inga VITE_-variabler kvar"
```

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "chore: build for the new backend and replace the gh-pages deploy"
```

---

### Task 15: Röktest mot Docker

**Files:**
- Ingen kodändring förväntad. Rättningar som behövs görs i berörd fil.

**Interfaces:**
- Consumes: hela systemet.
- Produces: bekräftelse att flödet fungerar end-to-end.

- [ ] **Step 1: Starta miljön och seeda ett adminkonto**

```bash
docker compose -f docker/docker-compose.yml up -d --build
docker compose -f docker/docker-compose.yml exec -T php composer install
docker compose -f docker/docker-compose.yml exec -T db mariadb -uroot -proot vacation < db/schema.sql
docker compose -f docker/docker-compose.yml exec php php db/create-admin.php
```

- [ ] **Step 2: Verifiera vakten utan session**

```bash
curl -i http://localhost:8080/api/bootstrap
```

Förväntat: `401` med `{"error":{"code":"unauthenticated",...}}` — inte en Apache-felsida.

- [ ] **Step 3: Logga in och kontrollera cookien**

```bash
curl -i -c cookies.txt -X POST http://localhost:8080/api/session \
  -H "Content-Type: application/json" \
  -d '{"email":"DIN@EPOST","password":"DITT-LÖSENORD"}'
```

Förväntat: `200`, och `Set-Cookie` med `HttpOnly` och `SameSite=Strict`.
**`Secure` saknas här, och ska göra det:** dev-`config.php` sätter
`cookie_secure => false` eftersom Docker kör ren HTTP och browsern annars aldrig
sparar cookien. Kontrollera att produktionens `config.php` har `true` innan
deploy — det är den enda skillnaden mellan miljöerna.

- [ ] **Step 4: Kör igenom kalenderflödet i browsern**

Starta `npm run dev`, logga in, skapa en grupp, skapa en person, lägg in en
semester, ladda om sidan och kontrollera att raden finns kvar.

- [ ] **Step 5: Verifiera att en annan användare får 403**

Skapa ett andra konto via adminpanelen, logga in som det, och försök ändra
den första personens semester. Förväntat: felmeddelandet "Du får bara ändra
din egen semester."

- [ ] **Step 6: Commit eventuella rättelser**

```bash
git add -A
git commit -m "fix: address issues found during end-to-end smoke testing"
```

---

## Efter planen

Deployen till Synology Web Station följer spec §11 och görs manuellt första
gången: databas och användare, schema, `create-admin.php`, PHP-profil med
`pdo_mysql` och `opcache`, document root på `/volume1/web/vacation/public`,
Let's Encrypt-certifikat, och **verifiering av `AllowOverride` innan något
annat**. Kontrollera också att Hyper Backup-jobbet klockan 03:00 omfattar den
nya databasen.
