# Semesterkalender — omläggning till PHP + MariaDB

**Datum:** 2026-09-16
**Status:** Godkänd design
**Ersätter:** datalagrings- och autentiseringsdelarna av
`2026-06-18-semesterkalender-design.md`. Kalendervyn, grupperingen och
ISO-veckologiken i det dokumentet gäller oförändrat.

## 1. Bakgrund och syfte

Den ursprungliga designen lagrade data i två JSON-filer i ett GitHub-repo och
skrev till dem via GitHub API från browsern. Premissen bär inte: en PAT och ett
delat lösenord måste bäddas in i bundlen, varje ändring blir en commit, och
samtidiga skrivningar hanteras med refetch-merge-retry mot `409 Conflict`.

Appen flyttar därför till egen hosting: Synology Web Station med Apache 2.4,
PHP 8.2 och MariaDB 10.11.11. Data läggs i en relationsdatabas med riktiga
nycklar och constraints, och autentiseringen flyttar till servern.

Tre saker driver omläggningen:

1. **Inga hemligheter i bundlen.** PAT och lösenord försvinner ur klienten.
2. **Riktig samtidighet.** Två personer som redigerar semester rör olika rader.
   Konfliktlogiken behövs inte längre och tas bort.
3. **Garantier i databasen** i stället för i frontend — kaskader, unika nycklar
   och intervallvalidering upprätthålls av MariaDB.

Appen exponeras publikt mot internet, vilket höjer kraven på autentisering
jämfört med den ursprungliga designen.

## 2. Teknisk stack

- **Frontend:** oförändrad — Vue 3, Vite, Tailwind + daisyUI, `@mdi/font`, Vitest
- **Backend:** PHP 8.2, front controller utan ramverk, PDO
- **Databas:** MariaDB 10.11.11 (LTS-raden på NAS:en)
- **Webbserver:** Apache 2.4 via Synology Web Station
- **Dev-miljö:** Docker Desktop — `php:8.2-apache` och `mariadb:10.11.11`
- **Backend-tester:** PHPUnit som utvecklingsberoende

Composer används enbart för PHPUnit. `vendor/` deployas aldrig, och
körtidskoden har noll externa beroenden.

## 3. Datamodell

Tre tabeller plus en för rate limiting. `persons` blir `users` eftersom de nu har
lösenord, och `groups` blir `teams` — specen kallar dem redan "grupper (team)",
och `GROUPS` är ett nyckelord som annars måste citeras i varje query.

```sql
CREATE TABLE teams (
  id         CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  name       VARCHAR(100) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
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

CREATE TABLE vacations (
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

CREATE TABLE login_attempts (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email        VARCHAR(255) NOT NULL,
  ip           VARBINARY(16) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX ix_attempt_email (email, attempted_at),
  INDEX ix_attempt_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Vad databasen garanterar som JSON-filerna inte kunde

- `ON DELETE CASCADE` — en borttagen person tar sin semester med sig. I dag är
  det två separata skrivningar i `removePerson`, där den andra kan misslyckas.
- `ON DELETE SET NULL` — ett borttaget team flyttar medlemmarna till
  "Ogrupperade". Exakt vad `removeGroup` gör för hand i dag.
- `CHECK (end_date >= start_date)` — validering som i dag bara finns i frontend.
  MariaDB upprätthåller `CHECK` på riktigt från 10.2.1.
- Unik e-post och unikt teamnamn, vilket ingen JSON-fil kunde upprätthålla.

### Designbeslut

- **UUID i stället för AUTO_INCREMENT.** Dyrare index, men URL:erna blir
  icke-gissningsbara nu när appen ligger publikt, och befintlig frontend-kod och
  tester räknar redan med UUID-strängar. Id:n genereras nu av servern, inte av
  `crypto.randomUUID()` i browsern.
- **E-post är inloggningsidentiteten**, inte namnet. Två kollegor kan heta Anna,
  och en framtida SSO-koppling sker på e-postadress — då blir övergången osynlig
  för användarna. `name` är ren visningstext utan unikhetskrav.
- **`password_hash` är nullbar** för att ett framtida SSO-provisionerat konto
  inte ska tvinga fram ett påhittat dummy-hash. Lösenordsinloggning mot ett konto
  utan hash avvisas med ett meddelande som pekar på Microsoft-inloggning.
- **`sso_id` reserveras nu men används inte.** Se
  [issue #2](https://github.com/nord-/vactionnode/issues/2). Kolumnen ska
  innehålla `oid`-claimen från Entra ID, inte `sub`: `oid` är samma GUID för
  användaren i hela tenanten, medan `sub` är unikt per applikation och byts om
  appregistreringen görs om.
- **"Ogrupperade" förblir syntetisk** — `team_id IS NULL`, ingen rad i `teams`.
  Oförändrat från ursprungsspecen, och `grouping.js` fungerar som den är.
- **E-post normaliseras till gemener** vid skrivning. Tabellens kollation är
  skiftlägesokänslig, så `UNIQUE (email)` matchar redan oberoende av versaler.

## 4. Autentisering och rättigheter

### Konton

Konton skapas av en administratör. Det finns ingen självregistrering och ingen
inbjudningskod. Det första adminkontot seedas med ett CLI-script vid
installationen; utan det finns ingen väg in i appen.

### Rättighetsmodell

Modellen är tvådelad, vilket gör den lätt att hålla rätt på i vakten:

- **Egen semester** — det enda en vanlig användare får skriva.
- **Allt annat** — konton, team och teamtillhörighet — kräver admin.

En administratör kan dessutom ändra vems semester som helst, så att rättelser går
att göra när någon är borta eller har slutat. Detta ersätter ursprungsspecens
öppna ändringsmodell, som byggde på att ingen identitet fanns.

Teamtillhörighet ändras enbart via `PUT /api/users/{id}`. Det finns ingen
teamväljare i huvudvyn och ingen `/api/users/me`-endpoint.

### Sessionen

- PHP-session med cookie satt till `HttpOnly`, `Secure` och `SameSite=Strict`.
- `session.use_strict_mode=1` och `session_regenerate_id(true)` vid inloggning,
  som skydd mot session fixation.
- Cookieparametrarna sätts i koden med `session_set_cookie_params()`, inte i
  Web Stations PHP-profil, så att Docker och NAS beter sig likadant och
  konfigurationen följer med i git.
- `session_write_close()` anropas omedelbart efter att användaren lästs ut. PHP:s
  filbaserade sessionslås serialiserar annars parallella requests från samma
  användare — kännbart på svag hårdvara.

### Skydd mot missbruk

- **Rate limiting** på `POST /api/session`: max fem misslyckade försök per
  e-postadress och per IP inom femton minuter, därefter `429`. Räknaren ligger i
  `login_attempts`.
- **Svaret är identiskt** för okänd e-post och fel lösenord. Annars blir
  endpointen en kontolista.
- **Gallring.** `login_attempts` växer obegränsat annars. Rader äldre än ett dygn
  raderas opportunistiskt vid inloggningsförsök, så ingen schemalagd städning
  behövs.
- **CSRF:** `SameSite=Strict` täcker huvudfallet. Därutöver kräver alla
  skrivningar `Content-Type: application/json`, vilket ett HTML-formulär från en
  annan sajt inte kan sätta utan preflight — och preflighten faller på CORS.
- **Felsvar** har formen `{"error":{"code":"...","message":"..."}}` och innehåller
  aldrig stacktrace. `display_errors` är av; allt loggas i stället.

## 5. API-yta

Alla svar är JSON. Alla endpoints utom `POST /api/session` kräver en giltig
session.

| Metod | Sökväg | Behörighet | Beskrivning |
|---|---|---|---|
| `POST` | `/api/session` | öppen | Logga in med e-post och lösenord |
| `GET` | `/api/session` | inloggad | Returnerar inloggad användare |
| `DELETE` | `/api/session` | inloggad | Logga ut |
| `GET` | `/api/bootstrap?from=&to=` | inloggad | Team, personer och semester i en request |
| `POST` | `/api/vacations` | inloggad | Skapar rad; `user_id` från sessionen om ej admin |
| `PUT` | `/api/vacations/{id}` | ägare eller admin | Ändrar datumintervall |
| `DELETE` | `/api/vacations/{id}` | ägare eller admin | Tar bort rad |
| `POST` | `/api/users` | admin | Skapar konto |
| `PUT` | `/api/users/{id}` | admin | Namn, e-post, team, adminflagga, lösenord |
| `DELETE` | `/api/users/{id}` | admin | Tar bort konto och dess semester |
| `POST` | `/api/teams` | admin | Skapar team |
| `PUT` | `/api/teams/{id}` | admin | Byter namn |
| `DELETE` | `/api/teams/{id}` | admin | Tar bort team; medlemmar blir ogrupperade |

### `GET /api/bootstrap`

Ersätter dagens två parallella filhämtningar. `from` och `to` begränsar
semesterraderna till det visade intervallet, som enligt ursprungsspecen är högst
tre månader. Saknad data returneras som tomma listor, inte som fel — samma regel
som ursprungsspecens "tomt dataset, inte krasch".

### Statuskoder

`200` vid lyckad läsning eller ändring, `201` vid skapande, `401` utan giltig
session, `403` vid otillräcklig behörighet, `404` för okänd resurs, `422` vid
valideringsfel, `429` vid för många inloggningsförsök.

`409 Conflict` förekommer inte längre. Radnivåskrivning gör konflikthanteringen
onödig, och den logiken tas bort ur klienten.

## 6. Front controller och vakten

`public/api/index.php` är enda ingången. `.htaccess` skickar alla anrop dit, och
en `match`-baserad routingtabell mappar metod och sökväg till en handler.

Vakten kör tre steg före varje handler:

1. **Session.** Saknas `$_SESSION['user_id']` svarar den `401` direkt, utom för
   `POST /api/session`. Ingen handler kan köras utan att ha passerat vakten —
   det är hela skälet till att en front controller valdes framför en fil per
   resurs, där en glömd rad överst i en fil ger ett öppet endpoint.
2. **`session_write_close()`** så snart användaren lästs ut.
3. **Behörighetskontroll.** Routingtabellen deklarerar `owner` eller `admin` per
   endpoint. Vakten jämför mot sessionen och svarar `403` vid miss. Regeln ligger
   i tabellen, inte i handlern.

Ägarskap kontrolleras dessutom mot databasen, inte mot vad klienten påstår:

```sql
UPDATE vacations SET start_date = ?, end_date = ?
WHERE id = ? AND (user_id = ? OR ? = 1)
```

Noll påverkade rader ger `403`. Då finns ingen väg runt kontrollen även om en
handler skrivs slarvigt.

## 7. Projektstruktur

Web Stations document root pekas på en underkatalog, så att PHP-koden och
konfigurationen ligger fysiskt utanför det Apache kan servera i stället för att
skyddas av en `.htaccess`-regel som kan glömmas vid en ommappning.

```
public/                 <- document root
  index.html            <- byggd frontend
  assets/
  api/
    index.php           <- front controller, enda PHP-filen i docroot
    .htaccess
app/                    <- inte nåbar via webben
  Router.php
  Db.php
  Auth.php
  Http.php
  Handlers/
    SessionHandler.php
    BootstrapHandler.php
    VacationHandler.php
    UserHandler.php
    TeamHandler.php
config.php              <- DB-uppgifter, ej i git
config.php.example      <- mall med tomma värden, i git
db/
  schema.sql
  create-admin.php
docker/
  docker-compose.yml
  Dockerfile
src/                    <- frontend, oförändrad struktur
tests/                  <- Vitest
tests-php/              <- PHPUnit
```

Autoloading sker med ett `spl_autoload_register` på ett tiotal rader.

## 8. Frontend-förändringar

Det utåtriktade interfacet i `dataStore.js` behålls, så `useStore.js` och samtliga
kalenderkomponenter är orörda.

**Tas bort:** `src/lib/githubClient.js`, `src/lib/localClient.js`,
`src/lib/identity.js`, `src/components/PersonSelectView.vue`,
`data/registry.json`, `data/vacations.json`. Sessionen är identiteten, så
"Välj dig"-vyn är inte längre meningsfull.

**Skrivs om:** `dataStore.js` mot en ny `apiClient.js` som kapslar `fetch`.
`auth.js` anropar `/api/session` i stället för att jämföra lösenord i browsern;
`localStorage.authenticated` utgår. `LoginView.vue` får ett e-postfält.

**Optimeras:** `useStore.action()` kör i dag `refresh()` efter varje mutation, så
hela registret och alla semesterrader laddas om när ett datum flyttas. Endpoints
returnerar i stället den skapade eller ändrade raden, och storen uppdateras
lokalt. Full omladdning sker bara vid appstart och vid byte av datumintervall.

**Konfiguration:** samtliga `VITE_`-variabler utgår — `VITE_PASSWORD`,
`VITE_GITHUB_PAT`, `VITE_GITHUB_OWNER`, `VITE_GITHUB_REPO`, `VITE_GITHUB_BRANCH`,
`VITE_DATA_BACKEND` och de två filsökvägarna. `src/config.js` krymper till en
API-bassökväg. Frontend innehåller därefter ingen hemlighet alls.

All beräkning stannar i browsern: ISO-veckor, kolumngenerering och gruppering
ligger kvar i `dates.js` och `grouping.js`. Servern levererar rader, inget annat.

## 9. Dev-miljö

Docker Compose kör `php:8.2-apache` med `pdo_mysql` och `opcache` påslaget, plus
`mariadb:10.11.11`. Versionen är exakt pinnad, inte `10.11`, så att miljöerna inte
glider isär när taggen uppdateras. Compose-filen mappar `public/` som docroot och
`app/` som syskonkatalog, precis som på NAS:en — samma relativa sökvägar i båda
miljöerna.

Vite kör på 5173 med `server.proxy` som skickar `/api` vidare till
`localhost:8080`. Samma origin ur browserns perspektiv, så ingen
CORS-konfiguration behövs — varken lokalt eller i produktion, där Apache serverar
både frontend och API.

## 10. Testning

### Frontend

Nio befintliga testfiler är orörda: `dates`, `grouping`, `validation`,
`vacationLookup`, `useDefaultRange`, `CalendarGrid`, `CalendarControls`,
`VacationModal` och `smoke`. Att de förblir gröna genom hela omläggningen är
kvittot på att datalagret verkligen var isolerat.

Fyra raderas tillsammans med koden de testar: `githubClient.test.js`,
`localClient.test.js`, `identity.test.js`, `PersonSelectView.test.js`.

Fyra skrivs om: `auth.test.js`, `dataStore.test.js`, `LoginView.test.js`,
`useStore.test.js`.

En tillkommer: `apiClient.test.js` — att `401` loggar ut, `403` ger ett begripligt
felmeddelande, `422` visar valideringsfelet, och att nätverksfel inte kraschar
vyn.

Inget Vitest-test rör nätverket; `fetch` mockas genomgående.

### Backend

PHPUnit körs som integrationstester mot en riktig MariaDB i Docker. Databasen
`vacation_test` får `schema.sql`, och varje test körs i en transaktion som rullas
tillbaka efteråt. Poängen med omläggningen var att flytta garantier in i
databasen — då måste testerna fråga databasen om den håller dem.

**Vakt-matrisen** testas för varje endpoint mot fyra anropare:

| Endpoint | Anonym | Inloggad, ej ägare | Ägare | Admin |
|---|---|---|---|---|
| `GET /api/bootstrap` | 401 | 200 | 200 | 200 |
| `POST /api/vacations` | 401 | 201 (egen) | 201 | 201 |
| `PUT` `DELETE /api/vacations/{id}` | 401 | 403 | 200 | 200 |
| `POST` `PUT` `DELETE /api/users` | 401 | 403 | 403 | 200 |
| `POST` `PUT` `DELETE /api/teams` | 401 | 403 | 403 | 200 |

Därutöver fyra tester på det som bara går att verifiera mot riktig databas och
riktig session:

1. **Ägarkontroll i SQL** — `UPDATE ... WHERE id = ? AND user_id = ?` påverkar
   noll rader för fel användare, även om anropet tar sig förbi vakten.
2. **Kaskader** — borttagen användare tar sin semester med sig; borttaget team
   nollställer `team_id` i stället för att radera medlemmarna.
3. **Rate limiting** — sex felaktiga inloggningar ger `429`, och svaret är
   identiskt för okänd e-post och fel lösenord.
4. **Sessionsbyte** — `session_regenerate_id(true)` sker vid inloggning.

### Arbetsordning

TDD enligt `superpowers:test-driven-development`. Schemat först, sedan ett rött
test per endpoint innan handlern finns. Vakt-matrisen skrivs före den första
handlern, så att `403`-fallen är gröna av konstruktion och inte i efterhand.

## 11. Deploy och drift

### Engångsuppsättning

1. **Databas och användare.** Applikationskontot får `SELECT`, `INSERT`,
   `UPDATE` och `DELETE` på `vacation.*` — inte `GRANT ALL`. Applikationen
   behöver aldrig `DROP` eller `ALTER`, och ett läckt lösenord ska inte kunna
   radera schemat.
2. **Schema och första admin.** `mysql vacation < db/schema.sql`, sedan
   `php db/create-admin.php`, som frågar efter e-post och lösenord och skriver
   raden med `password_hash()`. Ingen hash hamnar i git.
3. **PHP-profil i Web Station.** Aktivera `pdo_mysql` och `opcache`.
4. **Web Service och portal.** Document root pekas på
   `/volume1/web/vacation/public`. Egen subdomän eller egen port, så att `base` i
   `vite.config.js` kan förbli `/`.
5. **HTTPS.** Let's Encrypt-certifikat via Kontrollpanelen och tvingande
   omdirigering från HTTP. `Secure`-flaggan gör appen obrukbar över ren HTTP,
   vilket är avsiktligt.

**`AllowOverride` verifieras direkt efter steg 4**, innan någon annan kod skrivs.
Det är den enda tekniska risken i valet av front controller. Lägg upp
`api/.htaccess` och anropa `/api/session`: svarar Apache `404` i stället för JSON
är overriden avslagen, och då byts arkitekturen till en fil per resurs.

### Repeterbar deploy

```bash
npm run build
# --exclude=api/ är obligatoriskt: dist/ innehåller inte api/, så ett --delete
# utan undantaget raderar front controllern och tar ner hela API:et.
rsync -az --delete --exclude=api/ dist/ NAS:/volume1/web/vacation/public/
rsync -az --delete app/ NAS:/volume1/web/vacation/app/
```

`db/` och `config.php` deployas en gång vid installationen och ingår inte i den
repeterbara deployen. `db/` ligger utanför document root och ska inte vara
nåbart via webben.

`gh-pages` avinstalleras och `npm run deploy` skrivs om. Rollback är
`git checkout` på föregående tagg följt av samma två kommandon.

### Konfiguration

`config.php` ligger utanför git; `config.php.example` checkas in med tomma
värden. Filen deployas en gång för hand, ägs av `http` och sätts till `chmod 640`.

### Härdning och drift

- **Port 3307 stängs utåt.** Databasen nås bara från `localhost` och via
  SSH-tunnel. Portvidarebefordran omfattar enbart 443.
- **Auto-block** i Kontrollpanelen mot upprepade misslyckade SSH- och
  DSM-inloggningar. Applikationens rate limiting skyddar bara `/api/session`.
- **Hyper Backup.** Det befintliga jobbet klockan 03:00 måste omfatta `vacation`.
  Har jobbet en explicit databaslista är den nya databasen obackuppad tills den
  läggs till.

### Röktest efter första deployen

1. `/api/session` utan cookie ger `401` med JSON, inte en Apache-felsida.
2. Inloggning sätter en cookie med `Secure`, `HttpOnly` och `SameSite=Strict`.
3. `GET /api/bootstrap` returnerar tomma listor utan att krascha.
4. Kalendervyn renderar, en semesterrad sparas och överlever omladdning.
5. Ett andra konto kan inte ändra det förstas semester.

## 12. Utanför omfattningen

- **Microsoft Entra ID-inloggning.** Kolumnerna finns, flödet gör det inte. Se
  [issue #2](https://github.com/nord-/vactionnode/issues/2).
- **Lösenordsåterställning via e-post.** Admin sätter nytt lösenord via
  `PUT /api/users/{id}`. NAS:en behöver ingen mailkonfiguration.
- **Att byta sitt eget lösenord.** Det finns ingen endpoint för det, eftersom
  `PUT /api/users/{id}` är admin-skyddad. En användare som vill byta lösenord får
  be en administratör. Detta är en medveten avgränsning som är billig att häva
  senare — den kostar en `PUT /api/users/me/password` som kräver nuvarande
  lösenord — men den är värd att ompröva innan appen exponeras publikt.
- **Migrering av befintlig data.** `registry.json` och `vacations.json` är tomma;
  det finns inget att flytta.
- **Roll- och gruppmappning från Entra ID.** `is_admin` förblir en kolumn som
  hanteras i applikationen.
- **CI/CD.** Deploy sker manuellt, som i ursprungsspecen.
- **Ändringshistorik och revisionslogg** på semesterrader.
