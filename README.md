# vactionnode

Semesterkalender — en Vue 3 + Vite-app där personer matar in sina semesterdagar och ser allas frånvaro i en Excel-liknande kalendervy. Personer organiseras i grupper (team). Backend är PHP 8.2 mot MariaDB, med sessionsbaserad inloggning.

## Funktioner

- Excel-stil kalendervy: månad, ISO-veckonummer, datum + veckodag, helger i egen färg
- Personer grupperade under grupp-headers (inkl. "Ogrupperade")
- Inmatning av semester via datumintervall (hela dagar), eller genom att klicka
  på en tom dag — i den egna raden, eller i vems rad som helst för en administratör
- Visningsintervall i hela månader, max 3 månader åt gången; kalendern fylls ut
  till hela ISO-veckor (måndag–söndag) med nedtonade utfyllnadsdagar
- Datum visas och matas in på användarens föredragna språk, med en lokaliserad
  datumväljare
- Gruppfilter
- Inloggning med e-post och lösenord mot databasen, och byte av eget lösenord
- Adminsida för konton, grupper och gruppmedlemskap

## Arkitektur

- **Frontend:** Vue 3 + Vite, byggs till statiska filer i `dist/`.
- **Backend:** PHP 8.2 under `app/`, exponerad via `public/api/index.php`
  (front controller) och `public/api/.htaccess`.
- **Databas:** MariaDB 10.11. Schema i `db/schema.sql`.
- **Config:** `config.php` (gitignorerad, se `config.php.example`) innehåller
  databasuppkoppling och övriga serverinställningar.

Se `docs/superpowers/specs/2026-09-16-php-mariadb-backend-design.md` för fullständig arkitektur och krav.

## Utveckling

Backend körs i Docker — värddatorn behöver varken `php` eller `composer`:

```bash
cp config.php.example config.php   # se värdena för Docker nedan
docker compose -f docker/docker-compose.yml up -d
docker compose -f docker/docker-compose.yml exec -T db mariadb -uroot -proot vacation < db/schema.sql
npm install
npm run dev
```

`config.php.example` är skriven för NAS:en. Mot Docker-miljön ska `config.php` ha
dessa värden i stället: databasen heter `db` och lyssnar på port 3306 inuti
compose-nätet, compose skapar bara kontot `root`/`root`, och
`cookie_secure` måste vara `false` — dev-miljön kör ren HTTP, och en
`Secure`-cookie sparas aldrig av browsern, så inloggningen ser ut att lyckas
men nästa anrop ger 401.

```php
'db' => ['host' => 'db', 'port' => 3306, 'name' => 'vacation', 'user' => 'root', 'pass' => 'root'],
'cookie_secure' => false,
```

Schemat måste läsas in i `vacation`-databasen innan första kontot skapas;
`db/reset-test-db.sh` laddar bara `vacation_test`.

`npm run dev` startar Vite på en egen port och proxar `/api` mot
`http://localhost:8080`, där PHP-containern lyssnar.

### Skapa första kontot

```bash
docker compose -f docker/docker-compose.yml exec php php db/create-admin.php
```

Skriptet frågar efter namn, e-post och lösenord och skapar en administratör.

### Testsviter

Frontend-testerna körs alltid lokalt utan nätverk:

```bash
npm test -- --run
```

PHP-testerna körs inne i containern:

```bash
docker compose -f docker/docker-compose.yml exec -T php composer test
```

## Bygge

```bash
npm run build
```

Bygger till `dist/` med `base: '/'`. `public/` är PHP:ns document root i det
här projektet, inte Vites — `publicDir: false` i `vite.config.js` ser till att
`vite build` inte kopierar `public/api/` in i `dist/`.

## Deploy

Deploy sker till en Synology Web Station-portal via rsync:

```bash
npm run deploy
```

Detta kör `scripts/deploy.sh`, som bygger, synkar `dist/` till portalens
`public/` (utan att röra `public/api/`), synkar `app/` separat och synkar
`public/api/` (front controllern `index.php` och `.htaccess`) för sig. `db/`
och `config.php` deployas manuellt vid installationen — se skriptet för
detaljer.
`rsync` krävs på maskinen som deployar; skriptet avbryter med ett tydligt fel
om det saknas.
