# vactionnode

Semesterkalender — en Vue 3 + Vite-app på GitHub Pages där personer matar in sina
semesterdagar och ser allas frånvaro i en Excel-liknande kalendervy. Personer
organiseras i grupper (team). GitHub API används som datalager — inget eget backend.

## Funktioner

- Excel-stil kalendervy: månad, ISO-veckonummer, datum + veckodag, helger i egen färg
- Personer grupperade under grupp-headers (inkl. "Ogrupperade")
- Inmatning av semester via datumintervall (hela dagar)
- Visningsintervall i hela månader, max 3 månader åt gången
- Gruppfilter
- Enkelt delat lösenord för åtkomst (inte riktig säkerhet)

## Datafiler

Appen läser och skriver två JSON-datafiler via GitHub API:

- **`data/registry.json`** — innehåller personer och grupper
- **`data/vacations.json`** — innehåller semesterposter

Vid start och vid varje uppdatering hämtas dessa filer från din GitHub-gren.
Ändringar sparas tillbaka via GitHub API. För att applikationen ska fungera korrekt
måste miljövariablerna i `.env` vara korrekt ifyllda:

- `VITE_GITHUB_OWNER` — repo-ägare (t.ex. `nord-`)
- `VITE_GITHUB_REPO` — repo-namn (t.ex. `vactionnode`)
- `VITE_GITHUB_PAT` — GitHub Personal Access Token med åtkomst till repot
- `VITE_GITHUB_BRANCH` — vilken branch datafiler ligger på (t.ex. `main`)
- `VITE_REGISTRY_PATH` — sökväg till registry.json (t.ex. `data/registry.json`)
- `VITE_VACATIONS_PATH` — sökväg till vacations.json (t.ex. `data/vacations.json`)
- `VITE_PASSWORD` — enkelt lösenord för app-åtkomst

Se `.env.example` för exempel på värden.

## Status

Under uppbyggnad. Designen finns i
[`docs/superpowers/specs/2026-06-18-semesterkalender-design.md`](docs/superpowers/specs/2026-06-18-semesterkalender-design.md).

## Utveckling

```bash
cp .env.example .env   # fyll i värden
npm install
npm run dev
```

## Deploy

Deploy sker manuellt till GitHub Pages med `gh-pages`-paketet från en miljö där
`.env` är ifylld (miljövariablerna bäddas in i bygget):

```bash
npm run deploy
```

Ingen CI/CD-pipeline ingår. PAT-scope `public_repo` räcker så länge repot är publikt.
