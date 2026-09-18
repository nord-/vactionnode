# vactionnode

Semesterkalender — en Vue 3 + Vite-app där personer matar in sina semesterdagar och ser allas frånvaro i en Excel-liknande kalendervy. Personer organiseras i grupper (team). Backend är PHP 8.2 mot MariaDB, med sessionsbaserad inloggning. Ingen GitHub-koppling längre — data lagras i en riktig databas.

## Funktioner

- Excel-stil kalendervy: månad, ISO-veckonummer, datum + veckodag, helger i egen färg
- Personer grupperade under grupp-headers (inkl. "Ogrupperade")
- Inmatning av semester via datumintervall (hela dagar)
- Visningsintervall i hela månader, max 3 månader åt gången
- Gruppfilter
- Inloggning med e-post och lösenord mot databasen

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
cp config.php.example config.php   # fyll i värden
docker compose -f docker/docker-compose.yml up -d
npm install
npm run dev
```

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
`public/` (utan att röra `public/api/`) och synkar `app/` separat. `db/` och
`config.php` deployas manuellt vid installationen — se skriptet för detaljer.
`rsync` krävs på maskinen som deployar; skriptet avbryter med ett tydligt fel
om det saknas.
