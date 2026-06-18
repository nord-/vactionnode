# Semesterkalender — Designdokument

**Datum:** 2026-06-18
**Status:** Godkänd design

## 1. Syfte

En webbapp för semesterplanering där personer matar in sina semesterdagar och ser
allas frånvaro i en Excel-liknande kalendervy. Personer organiseras i grupper (team)
så att man kan visa alla eller filtrera på en specifik grupp.

Appen är ett internt teamverktyg. Det finns ingen riktig användarsäkerhet — ett
delat lösenord skyddar mot oavsiktlig åtkomst, men vem som helst som loggat in kan
se och ändra allt.

## 2. Teknisk stack

- **Frontend:** Vue 3 + Vite
- **Hosting:** GitHub Pages (statisk), deploy via `gh-pages`-paketet
- **Datalagring:** två JSON-filer i ett GitHub-repo, läses/skrivs via GitHub API
- **Tester:** Vitest
- **Inget eget backend** — GitHub API fungerar som datalager

## 3. Datamodell

Data delas i två filer för att skilja sällan-ändrad data från ofta-skriven data och
minska skrivkonflikter.

### `data/registry.json` (ändras sällan)

```json
{
  "groups": [
    { "id": "uuid", "name": "Team A" }
  ],
  "persons": [
    { "id": "uuid", "name": "Anna", "groupId": "uuid" }
  ]
}
```

- En person utan `groupId` (eller med okänt `groupId`) hamnar i den syntetiska
  gruppen **"Ogrupperade"**, som alltid visas sist.

### `data/vacations.json` (skrivs ofta)

```json
{
  "vacations": [
    { "id": "uuid", "personId": "uuid", "start": "2025-06-16", "end": "2025-06-20" }
  ]
}
```

- `start` och `end` är inklusiva datum i ISO-format (`YYYY-MM-DD`).
- Hela dagar. Intervallet måste vara minst en dag (`end >= start`).

## 4. Konfiguration & autentisering

### Konfiguration

- **GitHub PAT** och **delat lösenord** lagras som Vite-miljövariabler
  (`VITE_GITHUB_PAT`, `VITE_PASSWORD`) — `.env` lokalt, GitHub Actions secrets vid
  deploy. De bäddas in i den byggda bundlen.
- **Avvägning:** en teknisk person kan extrahera dessa ur bundlen. För ett internt
  teamverktyg är det en accepterad risk. Lösenordet skyddar mot oavsiktlig åtkomst,
  inte mot en angripare.
- Repo-koordinater (owner, repo, filsökvägar, branch) konfigureras också via
  miljövariabler.

### Inloggningsflöde

1. Vid appstart: läs `localStorage`-flaggan `authenticated`.
2. Saknas den eller är `false` → visa inloggningssida.
3. Användaren anger lösenord → jämför mot `import.meta.env.VITE_PASSWORD`.
4. Korrekt → sätt `localStorage.authenticated = true`, fortsätt till appen.

### Person-flöde ("vem är jag?")

1. Efter inloggning: läs `personId` från `localStorage`.
2. Saknas → visa "Välj dig"-vy:
   - **Skapa ny:** skriv ditt namn → ny person skapas i `registry.json`
     (utan grupp → hamnar i "Ogrupperade").
   - **Välj befintlig:** välj från lista över redan skapade personer.
3. Spara valt `personId` i `localStorage`. Appen känner igen personen vid nästa besök.

## 5. Kalendervyn (Excel-stil)

### Layout

- **Rader = personer, kolumner = dagar.** Personer grupperas under grupp-header-rader.
- **Grupp-header:** en rad per grupp med gruppnamnet; gruppens personer listas indragna
  inunder. Alltid expanderade (ingen hopfällning). "Ogrupperade" sist.
- **Header-rader överst (uppifrån och ned):**
  1. Månad + år (t.ex. "JUNI 2025"), spänner över sina dagar
  2. ISO-veckonummer (t.ex. "v.23"), spänner över sina dagar
  3. Datum + veckodag i **samma kolumn** (datum överst, veckodag under)
- **Vänsterkolumn** (namn) är "fryst"/sticky vid horisontell scroll.

### Visuell kodning

- **Semesterdag:** ifylld cell.
- **Helg (lördag/söndag):** egen bakgrundsfärg på hela kolumnen, oavsett semester.
- Horisontell scroll när antalet kolumner överstiger skärmbredden.

### Tidsregler

- **Veckor börjar måndag** (ISO 8601). Veckonummer beräknas enligt ISO-standard
  (vecka 1 = veckan som innehåller årets första torsdag).
- Visningsintervall anges som **från-månad** och **till-månad**, alltid hela månader.
- **Max 3 månaders spann.** Till-månadsväljaren begränsas så att intervallet aldrig
  överstiger 3 månader.

### Filtrering

- Gruppfilter (dropdown) överst: "Alla grupper" eller en specifik grupp.
  Filtrerar vilka rader som visas.

## 6. Interaktionsflöden

### Lägg till semester

- Knapp "Lägg till semester" → formulär med startdatum + slutdatum (datumväljare).
- Validering: `end >= start` (minst en dag).
- Gäller alltid den inloggade personen (`personId` från `localStorage`).
- Sparas till `vacations.json` (se skrivlogik nedan).

### Redigera / ta bort semester

- Klick på ett semesterblock i kalendern → visa intervallet med möjlighet att ändra
  datum eller ta bort.
- **Vem som helst kan ändra/ta bort vems som helst** (matchar den öppna modellen).

### Hantera personer & grupper (inbyggt i huvudvyn)

- Skapa grupp, byt namn, ta bort.
- Skapa person, tilldela grupp, ta bort.
- Skrivs till `registry.json`.
- **Ta bort person med registrerad semester:** appen varnar och tar bort både
  personen och dess semestrar vid bekräftelse.

## 7. GitHub API-lager

Ett litet interface kapslar in all GitHub API-kommunikation (läs/skriv av de två
filerna) så att det kan mockas i tester.

### Läsning

- Hämta filinnehåll + `sha` via GitHub Contents API.
- Ingen auth krävs för läsning av ett publikt repo; PAT används ändå konsekvent.

### Skrivning (med retry vid konflikt)

GitHub Contents API kräver filens nuvarande `sha` vid skrivning. Vid samtidiga
skrivningar får den andra `409 Conflict`.

1. Hämta aktuell fil + `sha`.
2. Applicera ändringen på datat.
3. PUT med `sha`.
4. Vid `409`: hämta om fil + `sha`, slå ihop ändringen på nytt, försök igen.
5. Max ~3 försök, sedan visa felmeddelande.

## 8. Felhantering

- **Skrivkonflikt (409):** refetch + merge + retry (max ~3 försök).
- **Nätverksfel vid läsning:** felmeddelande med "försök igen"-knapp, blockera inte
  hela appen.
- **Rate limit (403):** fånga och visa tydligt meddelande.
- **Korrupt/saknad JSON-fil:** behandla som tomt dataset (tomma listor), inte krasch.

## 9. Testning

**Enhetstester (Vitest)** för ren logik, fristående från DOM/nätverk:

- ISO-veckonummer-beräkning (kantfall kring årsskiften är kritiska).
- Kalenderkolumn-generering från månadsintervall (måndag-start, helg-flaggor).
- Intervallvalidering (≥ en dag, max 3 månader).
- Gruppering av personer inkl. syntetisk "Ogrupperade".

**Komponenttester** hålls lätta — fokus på logiken ovan, inte pixel-perfekt rendering.

GitHub API-lagret mockas via dess interface.

## 10. Avgränsningar (YAGNI)

- Ingen riktig användarautentisering eller behörighetsstyrning.
- Ingen halvdagshantering — bara hela dagar.
- Ingen realtidssynk — data hämtas vid laddning och efter skrivning.
- Ingen historik/audit-logg över ändringar.
