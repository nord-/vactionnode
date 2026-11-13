# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Vad det här är

Semesterkalender — en Vue 3 + Vite SPA där personer matar in semesterdagar och
ser allas frånvaro i en Excel-liknande kalendervy. Personer organiseras i
grupper. Backend är PHP 8.2 mot MariaDB 10.11, deployad på en Synology Web
Station-portal.

Designdokumentet för migreringen till PHP/MariaDB-backendet ligger i
`docs/superpowers/specs/2026-09-16-php-mariadb-backend-design.md`.

## Viktigt: värddatorn har varken `php` eller `composer`

All PHP körs i Docker-containern. Kör aldrig `php` eller `composer` direkt på
värden — de finns inte där. Använd alltid
`docker compose -f docker/docker-compose.yml exec php ...` respektive
`docker compose -f docker/docker-compose.yml exec -T php composer ...`
(`-T` slår av TTY-allokering, vilket krävs när kommandot körs icke-interaktivt).

## Kommandon

Stacken är Vue 3 + Vite + Tailwind/daisyUI + Vitest på frontend, PHP 8.2 +
MariaDB på backend, ikoner via `@mdi/font`, datumfält via
`@vuepic/vue-datepicker` med `date-fns`-locales.

Frontend, körs direkt på värden med Node/npm:

- `npm run dev` — utvecklingsserver, proxar `/api` mot `http://localhost:8080`
- `npm run build` — produktionsbygge till `dist/`
- `npx vitest run` — kör alla Vitest-tester en gång (`npm test` startar
  watch-läge och avslutas aldrig)
- `npx vitest run path/to/file.test.js` — kör ett enskilt testfil
- `npx vitest -t "test name"` — kör ett enskilt test via namn
- `npm run deploy` — kör `scripts/deploy.sh`, bygger och rsyncar till NAS:en

Backend, körs i Docker-containern:

- `docker compose -f docker/docker-compose.yml up -d` — starta PHP + MariaDB
- `docker compose -f docker/docker-compose.yml exec -T php composer test` — kör PHPUnit
- `docker compose -f docker/docker-compose.yml exec php php db/create-admin.php` — skapa första kontot
- `db/reset-test-db.sh` — återställ testdatabasen från `db/schema.sql`

## Arkitektur

### Datalagring (MariaDB)

Schema i `db/schema.sql`: `teams`, `users` (med `team_id`, `is_admin`,
`password_hash`), `vacations` och `login_attempts` (inloggningsspärren i
`app/RateLimit.php`). Kolumnen `users.sso_id` håller Entra ID-tokenets
`oid` (aldrig `sub`, som är per app och byts om app-registreringen görs
om). All databasåtkomst går via PDO, kapslad
bakom klasser i `app/` (`Db`, `Persons`, m.fl.) så den kan testas isolerat
med PHPUnit mot `vacation_test`-databasen.

### API

`public/api/index.php` är front controller, routad via `app/Router.php` till
handlers under `app/Handlers/`. `public/api/.htaccess` skickar alla
API-anrop dit. `public/` är PHP:ns document root i det här projektet — inte
en katalog för statiska Vite-tillgångar.

### Autentisering & identitet

- **Inloggning:** e-post + lösenord mot `users`-tabellen (`password_hash`),
  sessionsbaserad via PHP-sessioner. Hanteras av `app/Auth.php`.
- **Microsoft (Entra ID):** authorization code flow med PKCE i
  `app/Handlers/SsoHandler.php`, påslagen när `sso`-blocket i `config.php` är
  ifyllt. Id-tokenet valideras i `app/Sso/IdToken.php` med `openssl_verify`
  mot JWKS:ens x5c — ingen JWT-dependency, eftersom `vendor/` inte deployas.
  `app/Sso/SsoAccounts.php` slår upp kontot på `oid`, sedan e-post (länkar och
  **rensar lösenordet**), och skapar annars ett konto utan admin och grupp.
  state/nonce/verifier ligger i en egen `SameSite=Lax`-kaka (`sso_tx`):
  sessionskakan är `Strict` och följer inte med på redirecten från Microsoft.
- **Identitet:** inloggad användares `personId` styr vilka semesterposter som
  visas som "mina".
- Ingen hemlighet bäddas längre in i frontend-bygget — `config.php` på
  servern (gitignorerad, se `config.php.example`) håller
  databasuppkoppling och övriga inställningar.

### Kalenderlogik (kritiska kantfall)

- **ISO 8601:** veckor börjar måndag; veckonummer = ISO (vecka 1 innehåller årets
  första torsdag). Årsskiften är det viktigaste kantfallet — täck med enhetstester.
- Visningsintervall väljs i **hela månader**, **max 3 månaders spann**.
  Rutnätet fylls sedan ut till hela ISO-veckor (`weekAlignedRange` i
  `src/lib/dates.js`): från måndagen på eller före första månaden till
  söndagen på eller efter sista. Utfyllnadsdagarna är nedtonade och saknar
  månadsrubrik, och semestrar hämtas för hela det utfyllda intervallet.
- Personer grupperas under grupp-header-rader. Personer utan `groupId` hamnar i en
  **syntetisk "Ogrupperade"-grupp** som alltid visas sist.
- Helger (lör/sön) får egen bakgrundsfärg på hela kolumnen.

### Rättighetsmodell

- En vanlig användare får bara skriva sin egen semester.
- Allt annat — konton, grupper och gruppmedlemskap — kräver administratör.
  Enda undantaget: när Microsoft-inloggningen är på får alla i tenanten ett
  konto vid första inloggningen, alltid utan admin och utan grupp (beslut
  2026-11-12, issue #2).
- En administratör får därutöver ändra semestern för vem som helst, så att
  rättelser går att göra när någon har slutat eller är borta.
- Vakten körs i `app/Router.php` före varje handler och upprepas i SQL:et som
  försvar på djupet (`WHERE id = ? AND (user_id = ? OR ? = 1)` i
  `app/Handlers/VacationHandler.php`); `tests-php/GuardMatrixTest.php` är
  tabellen som bevisar det.
- Appen är publikt exponerad — försvaga inte den här vakten utan att först
  ändra specen.

## Konventioner

- Conventional commits (`feat:`, `fix:`, `refactor:`, `docs:`, `test:`, `chore:`).
- UI-text, kodkommentarer och dokumentation på svenska; commit-meddelanden,
  PR-titlar och PR-beskrivningar på engelska. Det följer användarens globala
  regel om att allt utåtriktat ska vara på engelska, oavsett repo.
- Datum är undantaget från svensk UI-text: de formateras och tolkas enligt
  användarens föredragna språk (`navigator.languages[0]`, se
  `src/lib/locale.js` och `src/lib/dateFormat.js`), alltid i gregoriansk
  kalender. Hårdkoda inte svenska datumformat.
- Spaces (inte tabbar), CRLF-radslut — utom `.sh`-filer, som ska ha LF
  (se `.gitattributes`).
- Ren logik (ISO-veckor, kolumngenerering, validering, gruppering) hålls fristående
  från DOM/nätverk så den kan enhetstestas i Vitest.
