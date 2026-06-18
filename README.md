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

## Status

Under uppbyggnad. Designen finns i
[`docs/superpowers/specs/2026-06-18-semesterkalender-design.md`](docs/superpowers/specs/2026-06-18-semesterkalender-design.md).

## Utveckling

```bash
cp .env.example .env   # fyll i värden
npm install
npm run dev
```
