# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Vad det här är

Semesterkalender — en Vue 3 + Vite SPA på GitHub Pages där personer matar in
semesterdagar och ser allas frånvaro i en Excel-liknande kalendervy. Personer
organiseras i grupper. Inget eget backend: GitHub API används som datalager.

Projektet är ännu inte scaffoldat — koden byggs enligt designdokumentet i
`docs/superpowers/specs/2026-06-18-semesterkalender-design.md`. Läs det dokumentet
först; det är källan till sanning för arkitektur och krav.

## Kommandon

Stacken är Vue 3 + Vite + Tailwind/daisyUI + Vitest, ikoner via `@mdi/font`
(scaffoldas enligt planen). Förväntade kommandon:

- `npm run dev` — utvecklingsserver
- `npm run build` — produktionsbygge till `dist/`
- `npm test` — kör Vitest
- `npx vitest run path/to/file.test.js` — kör ett enskilt testfil
- `npx vitest -t "test name"` — kör ett enskilt test via namn
- `npm run deploy` — deploy till GitHub Pages via `gh-pages`-paketet

## Arkitektur

### Datalagring (GitHub API, ingen server)

Data ligger i två JSON-filer i repot, åtskilda för att minska skrivkonflikter:

- **`data/registry.json`** — `groups` + `persons` (ändras sällan)
- **`data/vacations.json`** — `vacations` (skrivs ofta)

All GitHub API-kommunikation kapslas bakom **ett interface** så det kan mockas i
tester. Läsning hämtar filinnehåll + `sha`; skrivning kräver `sha` och måste hantera
`409 Conflict` med **refetch + merge + retry** (max ~3 försök). Korrupt/saknad fil
behandlas som tomt dataset, inte krasch.

### Autentisering & identitet (ingen riktig säkerhet)

- **Lösenord:** delat lösenord för alla, jämförs mot `VITE_PASSWORD`. Vid lyckad
  inloggning sätts `localStorage.authenticated = true`.
- **Identitet:** `personId` sparas i `localStorage`. Saknas det visas "Välj dig"
  (skapa ny person eller välj befintlig).
- **PAT + lösenord** bäddas in i bundlen via Vite-miljövariabler. Detta är en
  medveten avvägning för ett internt verktyg — extrahera aldrig detta till loggar
  och anta inte att det skyddar mot en angripare.

### Kalenderlogik (kritiska kantfall)

- **ISO 8601:** veckor börjar måndag; veckonummer = ISO (vecka 1 innehåller årets
  första torsdag). Årsskiften är det viktigaste kantfallet — täck med enhetstester.
- Visningsintervall anges i **hela månader**, **max 3 månaders spann**.
- Personer grupperas under grupp-header-rader. Personer utan `groupId` hamnar i en
  **syntetisk "Ogrupperade"-grupp** som alltid visas sist.
- Helger (lör/sön) får egen bakgrundsfärg på hela kolumnen.

### Öppen ändringsmodell

Vem som helst som är inloggad kan ändra/ta bort vems som helst semester och hantera
personer/grupper. Detta är avsiktligt — designa inte in behörighetskontroller.

## Konventioner

- Conventional commits (`feat:`, `fix:`, `refactor:`, `docs:`, `test:`).
- Svenska i UI-text, commit-meddelanden och kommentarer.
- Spaces (inte tabbar), CRLF-radslut.
- Ren logik (ISO-veckor, kolumngenerering, validering, gruppering) hålls fristående
  från DOM/nätverk så den kan enhetstestas i Vitest.
