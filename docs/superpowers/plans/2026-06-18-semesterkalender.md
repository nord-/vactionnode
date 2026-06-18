# Semesterkalender Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bygg en Vue 3 + Vite SPA på GitHub Pages där personer matar in semesterdagar och ser allas frånvaro i en Excel-liknande kalendervy med grupper.

**Architecture:** Ren, DOM-/nätverksfri logik (datum, validering, gruppering) ligger i `src/lib/` och enhetstestas med Vitest. GitHub API-lagret kapslas bakom ett injicerbart interface (`githubClient`) som dataStore använder; allt mockas i test. Vue-komponenter konsumerar ett reaktivt store-composable. Konfiguration läses från Vite-miljövariabler endast vid applikationens kant (`config.js`), aldrig i logikmoduler.

**Tech Stack:** Vue 3, Vite, Tailwind CSS, daisyUI, `@mdi/font`, Vitest, `@vue/test-utils`, `gh-pages`.

## Global Constraints

- **Språk:** Svenska i all UI-text, kommentarer och commit-meddelanden.
- **Kodstil:** Spaces (inte tabbar), CRLF-radslut. DRY/KISS/SOLID.
- **Commits:** Conventional commits (`feat:`, `fix:`, `docs:`, `test:`, `chore:`).
- **Ingen AI-attribution** i commits.
- **Branch:** Arbeta på en feature-gren, inte direkt på `main`.
- **Datumformat:** Inklusiva ISO-datum `YYYY-MM-DD`. Månad representeras som `YYYY-MM`.
- **ISO 8601:** Veckor börjar måndag; veckonummer enligt ISO (vecka 1 = veckan med årets första torsdag).
- **UUID:** `crypto.randomUUID()`.
- **Vite base path:** `/vactionnode/` (GitHub Pages-projektsida).
- **Logikmoduler tar beroenden som argument** — de importerar aldrig `config.js` eller `import.meta.env` direkt (testbarhet).
- **Öppen modell:** vem som helst inloggad kan ändra/ta bort allt. Bygg inga behörighetskontroller.

---

### Task 1: Scaffold projekt (Vite + Vue + Tailwind + daisyUI + Vitest)

**Files:**
- Create: `package.json`, `vite.config.js`, `index.html`, `postcss.config.js`, `tailwind.config.js`
- Create: `src/main.js`, `src/App.vue`, `src/style.css`
- Create: `src/config.js`
- Test: `tests/smoke.test.js`

**Interfaces:**
- Consumes: inget (första task).
- Produces: `config` (default export object) från `src/config.js` med formen
  `{ password, github: { token, owner, repo, branch, registryPath, vacationsPath } }`.
  Fungerande `npm run dev`, `npm test`, `npm run build`.

- [ ] **Step 1: Initiera npm och installera beroenden**

```bash
git checkout -b feature/semesterkalender
npm init -y
npm install vue
npm install -D vite @vitejs/plugin-vue vitest @vue/test-utils jsdom tailwindcss@3 postcss autoprefixer daisyui@4 gh-pages
npm install @mdi/font
```

- [ ] **Step 2: Skriv `package.json`-scripts**

Redigera `package.json` så `"scripts"` blir:

```json
{
  "type": "module",
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "preview": "vite preview",
    "test": "vitest",
    "deploy": "vite build && gh-pages -d dist"
  }
}
```

- [ ] **Step 3: Skriv `vite.config.js`**

```js
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  base: '/vactionnode/',
  plugins: [vue()],
  test: {
    environment: 'jsdom',
    globals: true,
  },
});
```

- [ ] **Step 4: Skriv Tailwind/PostCSS-config**

`tailwind.config.js`:

```js
import daisyui from 'daisyui';

/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js}'],
  theme: { extend: {} },
  plugins: [daisyui],
};
```

> Obs: `package.json` har `"type": "module"`, så configfilerna är ESM. Använd
> `import`, aldrig `require()` — det senare kraschar bygget.

`postcss.config.js`:

```js
export default {
  plugins: {
    tailwindcss: {},
    autoprefixer: {},
  },
};
```

- [ ] **Step 5: Skriv `index.html`, `src/style.css`, `src/main.js`, `src/App.vue`**

`index.html`:

```html
<!doctype html>
<html lang="sv">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Semesterkalender</title>
  </head>
  <body>
    <div id="app"></div>
    <script type="module" src="/src/main.js"></script>
  </body>
</html>
```

`src/style.css`:

```css
/* @import måste stå före övriga regler enligt CSS-spec. */
@import '@mdi/font/css/materialdesignicons.css';
@tailwind base;
@tailwind components;
@tailwind utilities;
```

`src/main.js`:

```js
import { createApp } from 'vue';
import App from './App.vue';
import './style.css';

createApp(App).mount('#app');
```

`src/App.vue`:

```vue
<template>
  <div class="p-4">
    <h1 class="text-2xl font-bold">Semesterkalender</h1>
  </div>
</template>

<script setup></script>
```

- [ ] **Step 6: Skriv `src/config.js`**

```js
const env = import.meta.env;

export default {
  password: env.VITE_PASSWORD,
  github: {
    token: env.VITE_GITHUB_PAT,
    owner: env.VITE_GITHUB_OWNER,
    repo: env.VITE_GITHUB_REPO,
    branch: env.VITE_GITHUB_BRANCH || 'main',
    registryPath: env.VITE_REGISTRY_PATH || 'data/registry.json',
    vacationsPath: env.VITE_VACATIONS_PATH || 'data/vacations.json',
  },
};
```

- [ ] **Step 7: Skriv smoke-test**

`tests/smoke.test.js`:

```js
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import App from '../src/App.vue';

describe('App', () => {
  it('renderar rubriken', () => {
    const wrapper = mount(App);
    expect(wrapper.text()).toContain('Semesterkalender');
  });
});
```

- [ ] **Step 8: Kör test och verifiera PASS**

Run: `npm test -- --run`
Expected: 1 passed (smoke.test.js).

- [ ] **Step 9: Verifiera bygge**

Run: `npm run build`
Expected: bygge lyckas, `dist/` skapas utan fel.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "chore: scaffolda Vue + Vite + Tailwind/daisyUI + Vitest"
```

---

### Task 2: Datum- och kalenderlogik (`src/lib/dates.js`)

**Files:**
- Create: `src/lib/dates.js`
- Test: `tests/dates.test.js`

**Interfaces:**
- Consumes: inget.
- Produces:
  - `isoWeekNumber(date: Date): number`
  - `isoWeekYear(date: Date): number` — ISO-veckans år (årtalet för veckans torsdag). Behövs för att gruppera kolumner per vecka korrekt över årsskiften.
  - `enumerateMonths(fromMonth: string, toMonth: string): string[]` — `'YYYY-MM'`-strängar inklusivt.
  - `monthSpanCount(fromMonth: string, toMonth: string): number` — antal månader inklusivt.
  - `generateColumns(fromMonth: string, toMonth: string): DayColumn[]` där
    `DayColumn = { iso: string, day: number, weekdayLabel: string, weekdayIndex: number, isWeekend: boolean, isoWeek: number, isoWeekYear: number, monthKey: string, monthLabel: string }`.
    `weekdayIndex`: Må=0…Sö=6. `monthKey`: `'YYYY-MM'`. `monthLabel`: t.ex. `'JUNI 2025'`.

- [ ] **Step 1: Skriv failande test för `isoWeekNumber`**

`tests/dates.test.js`:

```js
import { describe, it, expect } from 'vitest';
import { isoWeekNumber, isoWeekYear, enumerateMonths, monthSpanCount, generateColumns } from '../src/lib/dates.js';

describe('isoWeekNumber', () => {
  it('2025-06-16 (måndag) är vecka 25', () => {
    expect(isoWeekNumber(new Date(2025, 5, 16))).toBe(25);
  });
  it('2025-01-01 (onsdag) tillhör vecka 1', () => {
    expect(isoWeekNumber(new Date(2025, 0, 1))).toBe(1);
  });
  it('2021-01-01 (fredag) tillhör vecka 53 föregående år', () => {
    expect(isoWeekNumber(new Date(2021, 0, 1))).toBe(53);
  });
  it('2023-01-01 (söndag) tillhör vecka 52 föregående år', () => {
    expect(isoWeekNumber(new Date(2023, 0, 1))).toBe(52);
  });
  it('2020-12-31 (torsdag) är vecka 53', () => {
    expect(isoWeekNumber(new Date(2020, 11, 31))).toBe(53);
  });
});

describe('isoWeekYear', () => {
  it('2025-12-29 (måndag) tillhör ISO-vecka 1 år 2026', () => {
    // ISO-vecka 1/2026 börjar 2025-12-29 — veckans torsdag ligger i 2026.
    expect(isoWeekNumber(new Date(2025, 11, 29))).toBe(1);
    expect(isoWeekYear(new Date(2025, 11, 29))).toBe(2026);
  });
  it('2021-01-01 (fredag) tillhör ISO-vecka 53 år 2020', () => {
    expect(isoWeekNumber(new Date(2021, 0, 1))).toBe(53);
    expect(isoWeekYear(new Date(2021, 0, 1))).toBe(2020);
  });
  it('2025-06-16 tillhör ISO-år 2025', () => {
    expect(isoWeekYear(new Date(2025, 5, 16))).toBe(2025);
  });
});
```

- [ ] **Step 2: Kör test, verifiera FAIL**

Run: `npm test -- --run tests/dates.test.js`
Expected: FAIL — "isoWeekNumber is not a function".

- [ ] **Step 3: Implementera `isoWeekNumber` och `isoWeekYear`**

`src/lib/dates.js`:

```js
// Flyttar ett datum (UTC) till torsdagen i samma ISO-vecka.
function isoThursday(date) {
  const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
  const dayNum = (d.getUTCDay() + 6) % 7; // Må=0 … Sö=6
  d.setUTCDate(d.getUTCDate() - dayNum + 3);
  return d;
}

// ISO 8601-veckonummer. Vecka 1 = veckan som innehåller årets första torsdag.
export function isoWeekNumber(date) {
  const d = isoThursday(date);
  const firstThursday = new Date(Date.UTC(d.getUTCFullYear(), 0, 4));
  const firstDayNum = (firstThursday.getUTCDay() + 6) % 7;
  firstThursday.setUTCDate(firstThursday.getUTCDate() - firstDayNum + 3);
  const diffMs = d.getTime() - firstThursday.getTime();
  return 1 + Math.round(diffMs / (7 * 24 * 3600 * 1000));
}

// ISO-veckans år = årtalet för veckans torsdag.
export function isoWeekYear(date) {
  return isoThursday(date).getUTCFullYear();
}
```

- [ ] **Step 4: Kör test, verifiera PASS**

Run: `npm test -- --run tests/dates.test.js`
Expected: PASS (isoWeekNumber- och isoWeekYear-blocken).

- [ ] **Step 5: Skriv failande test för månadshjälpare**

Lägg till i `tests/dates.test.js`:

```js
describe('enumerateMonths / monthSpanCount', () => {
  it('listar månader inklusivt', () => {
    expect(enumerateMonths('2025-06', '2025-08')).toEqual(['2025-06', '2025-07', '2025-08']);
  });
  it('hanterar årsskifte', () => {
    expect(enumerateMonths('2025-12', '2026-01')).toEqual(['2025-12', '2026-01']);
  });
  it('räknar spann inklusivt', () => {
    expect(monthSpanCount('2025-06', '2025-08')).toBe(3);
    expect(monthSpanCount('2025-06', '2025-06')).toBe(1);
  });
});
```

- [ ] **Step 6: Kör test, verifiera FAIL**

Run: `npm test -- --run tests/dates.test.js`
Expected: FAIL — "enumerateMonths is not a function".

- [ ] **Step 7: Implementera månadshjälpare**

Lägg till i `src/lib/dates.js`:

```js
function parseMonth(monthKey) {
  const [year, month] = monthKey.split('-').map(Number);
  return { year, month }; // month: 1-12
}

function monthIndex(monthKey) {
  const { year, month } = parseMonth(monthKey);
  return year * 12 + (month - 1);
}

function formatMonth(index) {
  const year = Math.floor(index / 12);
  const month = (index % 12) + 1;
  return `${year}-${String(month).padStart(2, '0')}`;
}

export function monthSpanCount(fromMonth, toMonth) {
  return monthIndex(toMonth) - monthIndex(fromMonth) + 1;
}

export function enumerateMonths(fromMonth, toMonth) {
  const result = [];
  for (let i = monthIndex(fromMonth); i <= monthIndex(toMonth); i++) {
    result.push(formatMonth(i));
  }
  return result;
}
```

- [ ] **Step 8: Kör test, verifiera PASS**

Run: `npm test -- --run tests/dates.test.js`
Expected: PASS.

- [ ] **Step 9: Skriv failande test för `generateColumns`**

Lägg till i `tests/dates.test.js`:

```js
describe('generateColumns', () => {
  it('genererar en kolumn per dag i intervallet', () => {
    const cols = generateColumns('2025-06', '2025-08');
    expect(cols.length).toBe(30 + 31 + 31);
    expect(cols[0].iso).toBe('2025-06-01');
    expect(cols.at(-1).iso).toBe('2025-08-31');
  });
  it('flaggar helger och sätter måndag-index 0', () => {
    const cols = generateColumns('2025-06', '2025-06');
    const mon16 = cols.find((c) => c.iso === '2025-06-16');
    const sat21 = cols.find((c) => c.iso === '2025-06-21');
    const sun22 = cols.find((c) => c.iso === '2025-06-22');
    expect(mon16.weekdayIndex).toBe(0);
    expect(mon16.weekdayLabel).toBe('Må');
    expect(mon16.isWeekend).toBe(false);
    expect(sat21.isWeekend).toBe(true);
    expect(sun22.isWeekend).toBe(true);
  });
  it('sätter monthLabel, isoWeek och isoWeekYear', () => {
    const cols = generateColumns('2025-06', '2025-06');
    const mon16 = cols.find((c) => c.iso === '2025-06-16');
    expect(mon16.monthLabel).toBe('JUNI 2025');
    expect(mon16.monthKey).toBe('2025-06');
    expect(mon16.isoWeek).toBe(25);
    expect(mon16.isoWeekYear).toBe(2025);
  });
  it('sätter rätt isoWeekYear vid årsskifte', () => {
    const cols = generateColumns('2025-12', '2026-01');
    const dec29 = cols.find((c) => c.iso === '2025-12-29');
    expect(dec29.isoWeek).toBe(1);
    expect(dec29.isoWeekYear).toBe(2026);
  });
});
```

- [ ] **Step 10: Kör test, verifiera FAIL**

Run: `npm test -- --run tests/dates.test.js`
Expected: FAIL — "generateColumns is not a function".

- [ ] **Step 11: Implementera `generateColumns`**

Lägg till i `src/lib/dates.js`:

```js
const WEEKDAY_LABELS = ['Må', 'Ti', 'On', 'To', 'Fr', 'Lö', 'Sö'];
const MONTH_NAMES = [
  'JANUARI', 'FEBRUARI', 'MARS', 'APRIL', 'MAJ', 'JUNI',
  'JULI', 'AUGUSTI', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DECEMBER',
];

function toIso(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

export function generateColumns(fromMonth, toMonth) {
  const { year: fy, month: fm } = parseMonth(fromMonth);
  const { year: ty, month: tm } = parseMonth(toMonth);
  const start = new Date(fy, fm - 1, 1);
  const end = new Date(ty, tm, 0); // sista dagen i toMonth
  const columns = [];
  for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
    const weekdayIndex = (d.getDay() + 6) % 7; // Må=0 … Sö=6
    columns.push({
      iso: toIso(d),
      day: d.getDate(),
      weekdayLabel: WEEKDAY_LABELS[weekdayIndex],
      weekdayIndex,
      isWeekend: weekdayIndex >= 5,
      isoWeek: isoWeekNumber(d),
      isoWeekYear: isoWeekYear(d),
      monthKey: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`,
      monthLabel: `${MONTH_NAMES[d.getMonth()]} ${d.getFullYear()}`,
    });
  }
  return columns;
}
```

- [ ] **Step 12: Kör hela filen, verifiera PASS**

Run: `npm test -- --run tests/dates.test.js`
Expected: alla tester PASS.

- [ ] **Step 13: Commit**

```bash
git add src/lib/dates.js tests/dates.test.js
git commit -m "feat: lägg till ISO-datum- och kalenderkolumnlogik"
```

---

### Task 3: Validering (`src/lib/validation.js`)

**Files:**
- Create: `src/lib/validation.js`
- Test: `tests/validation.test.js`

**Interfaces:**
- Consumes: `monthSpanCount`, `enumerateMonths` från `src/lib/dates.js`.
- Produces:
  - `isValidVacationRange(start: string, end: string): boolean` — sant om `end >= start` (minst en dag). Ingen maxgräns.
  - `clampMonthRange(fromMonth, toMonth, changed: 'from' | 'to', maxMonths = 3): { from: string, to: string }`
    — justerar den **icke**-ändrade gränsen så spannet aldrig överstiger `maxMonths`.

- [ ] **Step 1: Skriv failande test**

`tests/validation.test.js`:

```js
import { describe, it, expect } from 'vitest';
import { isValidVacationRange, clampMonthRange } from '../src/lib/validation.js';

describe('isValidVacationRange', () => {
  it('godtar en enda dag', () => {
    expect(isValidVacationRange('2025-06-16', '2025-06-16')).toBe(true);
  });
  it('godtar end efter start', () => {
    expect(isValidVacationRange('2025-06-16', '2025-06-20')).toBe(true);
  });
  it('avvisar end före start', () => {
    expect(isValidVacationRange('2025-06-20', '2025-06-16')).toBe(false);
  });
  it('har ingen övre gräns', () => {
    expect(isValidVacationRange('2025-01-01', '2025-12-31')).toBe(true);
  });
});

describe('clampMonthRange', () => {
  it('lämnar oförändrat när spannet är inom max', () => {
    expect(clampMonthRange('2025-06', '2025-08', 'to')).toEqual({ from: '2025-06', to: '2025-08' });
  });
  it('när to ändras för långt fram flyttas from upp', () => {
    expect(clampMonthRange('2025-06', '2025-10', 'to')).toEqual({ from: '2025-08', to: '2025-10' });
  });
  it('när from ändras för långt bak flyttas to ned', () => {
    expect(clampMonthRange('2025-02', '2025-10', 'from')).toEqual({ from: '2025-02', to: '2025-04' });
  });
  it('hanterar to före from genom att klampa den ändrade kanten', () => {
    expect(clampMonthRange('2025-06', '2025-04', 'to')).toEqual({ from: '2025-04', to: '2025-04' });
  });
});
```

- [ ] **Step 2: Kör test, verifiera FAIL**

Run: `npm test -- --run tests/validation.test.js`
Expected: FAIL — "isValidVacationRange is not a function".

- [ ] **Step 3: Implementera**

`src/lib/validation.js`:

```js
import { monthSpanCount, enumerateMonths } from './dates.js';

export function isValidVacationRange(start, end) {
  return Boolean(start) && Boolean(end) && end >= start;
}

// Justerar den icke-ändrade kanten så spannet aldrig överstiger maxMonths.
// 'changed' anger vilken kant användaren just satte (den hålls fast).
export function clampMonthRange(fromMonth, toMonth, changed, maxMonths = 3) {
  let from = fromMonth;
  let to = toMonth;

  // Säkerställ from <= to genom att flytta den icke-ändrade kanten.
  if (from > to) {
    if (changed === 'to') from = to;
    else to = from;
  }

  if (monthSpanCount(from, to) > maxMonths) {
    if (changed === 'to') {
      // Håll to fast, flytta from framåt.
      const months = enumerateMonths(from, to);
      from = months[months.length - maxMonths];
    } else {
      // Håll from fast, flytta to bakåt.
      const months = enumerateMonths(from, to);
      to = months[maxMonths - 1];
    }
  }
  return { from, to };
}
```

- [ ] **Step 4: Kör test, verifiera PASS**

Run: `npm test -- --run tests/validation.test.js`
Expected: alla PASS.

- [ ] **Step 5: Commit**

```bash
git add src/lib/validation.js tests/validation.test.js
git commit -m "feat: lägg till semester- och visningsspann-validering"
```

---

### Task 4: Gruppering (`src/lib/grouping.js`)

**Files:**
- Create: `src/lib/grouping.js`
- Test: `tests/grouping.test.js`

**Interfaces:**
- Consumes: inget.
- Produces:
  - `UNGROUPED_ID = null` och `UNGROUPED_NAME = 'Ogrupperade'` (exporterade konstanter).
  - `groupPersons(groups: Group[], persons: Person[]): GroupSection[]` där
    `Group = { id, name }`, `Person = { id, name, groupId }`,
    `GroupSection = { group: { id, name }, persons: Person[] }`.
    Grupper i registrets ordning; en syntetisk `{ id: null, name: 'Ogrupperade' }` läggs **sist** och endast om den har personer. En person vars `groupId` inte matchar någon grupp räknas som ogrupperad.

- [ ] **Step 1: Skriv failande test**

`tests/grouping.test.js`:

```js
import { describe, it, expect } from 'vitest';
import { groupPersons, UNGROUPED_NAME } from '../src/lib/grouping.js';

const groups = [
  { id: 'g1', name: 'Team A' },
  { id: 'g2', name: 'Team B' },
];

describe('groupPersons', () => {
  it('placerar personer under rätt grupp i registrets ordning', () => {
    const persons = [
      { id: 'p1', name: 'Anna', groupId: 'g1' },
      { id: 'p2', name: 'Björn', groupId: 'g1' },
      { id: 'p3', name: 'Cecilia', groupId: 'g2' },
    ];
    const sections = groupPersons(groups, persons);
    expect(sections.map((s) => s.group.name)).toEqual(['Team A', 'Team B']);
    expect(sections[0].persons.map((p) => p.name)).toEqual(['Anna', 'Björn']);
  });

  it('lägger ogrupperade sist', () => {
    const persons = [
      { id: 'p1', name: 'Anna', groupId: 'g1' },
      { id: 'p4', name: 'David', groupId: null },
    ];
    const sections = groupPersons(groups, persons);
    expect(sections.at(-1).group.name).toBe(UNGROUPED_NAME);
    expect(sections.at(-1).group.id).toBe(null);
    expect(sections.at(-1).persons.map((p) => p.name)).toEqual(['David']);
  });

  it('behandlar okänt groupId som ogrupperad', () => {
    const persons = [{ id: 'p5', name: 'Eva', groupId: 'borttagen-grupp' }];
    const sections = groupPersons(groups, persons);
    expect(sections.at(-1).group.name).toBe(UNGROUPED_NAME);
    expect(sections.at(-1).persons.map((p) => p.name)).toEqual(['Eva']);
  });

  it('utelämnar Ogrupperade-sektionen när den är tom', () => {
    const persons = [{ id: 'p1', name: 'Anna', groupId: 'g1' }];
    const sections = groupPersons(groups, persons);
    expect(sections.some((s) => s.group.name === UNGROUPED_NAME)).toBe(false);
  });

  it('utelämnar tomma namngivna grupper', () => {
    const persons = [{ id: 'p3', name: 'Cecilia', groupId: 'g2' }];
    const sections = groupPersons(groups, persons);
    expect(sections.map((s) => s.group.name)).toEqual(['Team B']);
  });
});
```

- [ ] **Step 2: Kör test, verifiera FAIL**

Run: `npm test -- --run tests/grouping.test.js`
Expected: FAIL — "groupPersons is not a function".

- [ ] **Step 3: Implementera**

`src/lib/grouping.js`:

```js
export const UNGROUPED_ID = null;
export const UNGROUPED_NAME = 'Ogrupperade';

export function groupPersons(groups, persons) {
  const knownIds = new Set(groups.map((g) => g.id));
  const sections = [];

  for (const group of groups) {
    const members = persons.filter((p) => p.groupId === group.id);
    if (members.length > 0) {
      sections.push({ group: { id: group.id, name: group.name }, persons: members });
    }
  }

  const ungrouped = persons.filter((p) => !knownIds.has(p.groupId));
  if (ungrouped.length > 0) {
    sections.push({ group: { id: UNGROUPED_ID, name: UNGROUPED_NAME }, persons: ungrouped });
  }

  return sections;
}
```

- [ ] **Step 4: Kör test, verifiera PASS**

Run: `npm test -- --run tests/grouping.test.js`
Expected: alla PASS.

- [ ] **Step 5: Commit**

```bash
git add src/lib/grouping.js tests/grouping.test.js
git commit -m "feat: lägg till grupperingslogik med Ogrupperade-fallback"
```

---

### Task 5: GitHub API-klient (`src/lib/githubClient.js`)

**Files:**
- Create: `src/lib/githubClient.js`
- Test: `tests/githubClient.test.js`

**Interfaces:**
- Consumes: inget (tar all config + en `fetchImpl` som argument → testbart).
- Produces: `createGithubClient({ owner, repo, branch, token, fetchImpl })` som returnerar:
  - `readFile(path: string): Promise<{ data: object | null, sha: string | null }>`
    — parsar JSON. Vid 404 eller korrupt JSON: `{ data: null, sha: null }`.
  - `writeFile(path: string, mutate: (current: object) => object, opts?: { emptyValue?: object, maxRetries?: number }): Promise<object>`
    — läser fil+sha, kör `mutate` på datat (eller `emptyValue` om filen saknas), PUT med sha. Vid 409: läs om och kör `mutate` på nytt (last-write-wins). Returnerar slutligt skrivet data. Kastar `GithubApiError` med `.status` vid 403 eller efter uttömda försök.

- [ ] **Step 1: Skriv failande test**

`tests/githubClient.test.js`:

```js
import { describe, it, expect, vi } from 'vitest';
import { createGithubClient } from '../src/lib/githubClient.js';

function b64(str) {
  return btoa(unescape(encodeURIComponent(str)));
}
function jsonResponse(obj, sha) {
  return {
    ok: true,
    status: 200,
    json: async () => ({ content: b64(JSON.stringify(obj)), sha }),
  };
}

const base = { owner: 'o', repo: 'r', branch: 'main', token: 't' };

describe('readFile', () => {
  it('parsar innehåll och returnerar sha', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(jsonResponse({ groups: [] }, 'sha1'));
    const client = createGithubClient({ ...base, fetchImpl });
    const result = await client.readFile('data/registry.json');
    expect(result).toEqual({ data: { groups: [] }, sha: 'sha1' });
  });

  it('returnerar tomt vid 404', async () => {
    const fetchImpl = vi.fn().mockResolvedValue({ ok: false, status: 404 });
    const client = createGithubClient({ ...base, fetchImpl });
    expect(await client.readFile('x')).toEqual({ data: null, sha: null });
  });
});

describe('writeFile', () => {
  it('läser, muterar och PUTar med sha', async () => {
    const fetchImpl = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse({ items: [] }, 'sha1'))
      .mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({}) });
    const client = createGithubClient({ ...base, fetchImpl });
    const result = await client.writeFile('p', (d) => ({ items: [...d.items, 'a'] }));
    expect(result).toEqual({ items: ['a'] });
    const putCall = fetchImpl.mock.calls[1];
    const body = JSON.parse(putCall[1].body);
    expect(body.sha).toBe('sha1');
    expect(body.branch).toBe('main');
  });

  it('gör om mutate på färskt data vid 409', async () => {
    const fetchImpl = vi
      .fn()
      .mockResolvedValueOnce(jsonResponse({ items: [] }, 'sha1'))
      .mockResolvedValueOnce({ ok: false, status: 409 })
      .mockResolvedValueOnce(jsonResponse({ items: ['other'] }, 'sha2'))
      .mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({}) });
    const client = createGithubClient({ ...base, fetchImpl });
    const result = await client.writeFile('p', (d) => ({ items: [...d.items, 'mine'] }));
    expect(result).toEqual({ items: ['other', 'mine'] });
  });

  it('använder emptyValue när filen saknas', async () => {
    const fetchImpl = vi
      .fn()
      .mockResolvedValueOnce({ ok: false, status: 404 })
      .mockResolvedValueOnce({ ok: true, status: 200, json: async () => ({}) });
    const client = createGithubClient({ ...base, fetchImpl });
    const result = await client.writeFile('p', (d) => ({ items: [...d.items, 'a'] }), {
      emptyValue: { items: [] },
    });
    expect(result).toEqual({ items: ['a'] });
  });

  it('kastar med status vid 403', async () => {
    const fetchImpl = vi.fn().mockResolvedValue({ ok: false, status: 403 });
    const client = createGithubClient({ ...base, fetchImpl });
    await expect(client.writeFile('p', (d) => d, { emptyValue: {} })).rejects.toMatchObject({
      status: 403,
    });
  });
});
```

- [ ] **Step 2: Kör test, verifiera FAIL**

Run: `npm test -- --run tests/githubClient.test.js`
Expected: FAIL — "createGithubClient is not a function".

- [ ] **Step 3: Implementera**

`src/lib/githubClient.js`:

```js
export class GithubApiError extends Error {
  constructor(message, status) {
    super(message);
    this.name = 'GithubApiError';
    this.status = status;
  }
}

function encodeContent(str) {
  return btoa(unescape(encodeURIComponent(str)));
}
function decodeContent(b64) {
  return decodeURIComponent(escape(atob(b64)));
}

export function createGithubClient({ owner, repo, branch, token, fetchImpl = fetch }) {
  const baseUrl = `https://api.github.com/repos/${owner}/${repo}/contents`;
  const headers = {
    Accept: 'application/vnd.github+json',
    Authorization: `Bearer ${token}`,
  };

  async function readFile(path) {
    const res = await fetchImpl(`${baseUrl}/${path}?ref=${branch}`, { headers });
    if (res.status === 404) return { data: null, sha: null };
    if (!res.ok) throw new GithubApiError(`Läsfel ${res.status}`, res.status);
    const body = await res.json();
    try {
      return { data: JSON.parse(decodeContent(body.content)), sha: body.sha };
    } catch {
      return { data: null, sha: null }; // korrupt JSON → tomt dataset
    }
  }

  async function writeFile(path, mutate, { emptyValue = {}, maxRetries = 3 } = {}) {
    for (let attempt = 0; attempt < maxRetries; attempt++) {
      const { data, sha } = await readFile(path);
      const current = data ?? structuredClone(emptyValue);
      const next = mutate(current);
      const res = await fetchImpl(`${baseUrl}/${path}`, {
        method: 'PUT',
        headers,
        body: JSON.stringify({
          message: `Uppdatera ${path}`,
          content: encodeContent(JSON.stringify(next, null, 2)),
          branch,
          ...(sha ? { sha } : {}),
        }),
      });
      if (res.ok) return next;
      if (res.status === 409) continue; // konflikt → läs om och gör om mutate
      throw new GithubApiError(`Skrivfel ${res.status}`, res.status);
    }
    throw new GithubApiError('Skrivkonflikt kvarstod efter flera försök', 409);
  }

  return { readFile, writeFile };
}
```

- [ ] **Step 4: Kör test, verifiera PASS**

Run: `npm test -- --run tests/githubClient.test.js`
Expected: alla PASS.

- [ ] **Step 5: Commit**

```bash
git add src/lib/githubClient.js tests/githubClient.test.js
git commit -m "feat: lägg till GitHub Contents-klient med retry vid 409"
```

---

### Task 6: DataStore — domänoperationer (`src/lib/dataStore.js`)

**Files:**
- Create: `src/lib/dataStore.js`
- Test: `tests/dataStore.test.js`

**Interfaces:**
- Consumes: en klient med samma form som `createGithubClient` returnerar (`readFile`, `writeFile`); `registryPath` och `vacationsPath`.
- Produces: `createDataStore({ client, registryPath, vacationsPath })` som returnerar:
  - `loadRegistry(): Promise<{ groups, persons }>` (tomma listor om filen saknas)
  - `loadVacations(): Promise<{ vacations }>`
  - `addGroup(name): Promise<...>`, `renameGroup(id, name)`, `removeGroup(id)` (nullar `groupId` på medlemmar)
  - `addPerson(name, groupId = null)`, `setPersonGroup(id, groupId)`, `removePerson(id)` (tar även bort personens semestrar)
  - `addVacation(personId, start, end)`, `updateVacation(id, start, end)`, `removeVacation(id)`
  - Mutationer returnerar det skrivna filobjektet.

- [ ] **Step 1: Skriv failande test**

`tests/dataStore.test.js`:

```js
import { describe, it, expect, vi } from 'vitest';
import { createDataStore } from '../src/lib/dataStore.js';

// Fejkklient med data i minnet. writeFile kör mutate på aktuell fil.
function fakeClient(files) {
  return {
    readFile: vi.fn(async (path) => ({ data: files[path] ?? null, sha: 's' })),
    writeFile: vi.fn(async (path, mutate, { emptyValue } = {}) => {
      const current = files[path] ?? structuredClone(emptyValue ?? {});
      files[path] = mutate(current);
      return files[path];
    }),
  };
}

const paths = { registryPath: 'reg.json', vacationsPath: 'vac.json' };

describe('dataStore registry', () => {
  it('lägger till grupp och person', async () => {
    const files = {};
    const store = createDataStore({ client: fakeClient(files), ...paths });
    await store.addGroup('Team A');
    expect(files['reg.json'].groups[0].name).toBe('Team A');
    expect(files['reg.json'].groups[0].id).toBeTypeOf('string');
    const created = await store.addPerson('Anna');
    expect(files['reg.json'].persons[0]).toMatchObject({ name: 'Anna', groupId: null });
    expect(created).toMatchObject({ name: 'Anna', groupId: null });
    expect(created.id).toBe(files['reg.json'].persons[0].id);
  });

  it('removeGroup nullar groupId på medlemmar men raderar dem inte', async () => {
    const files = {
      'reg.json': {
        groups: [{ id: 'g1', name: 'Team A' }],
        persons: [{ id: 'p1', name: 'Anna', groupId: 'g1' }],
      },
    };
    const store = createDataStore({ client: fakeClient(files), ...paths });
    await store.removeGroup('g1');
    expect(files['reg.json'].groups).toEqual([]);
    expect(files['reg.json'].persons[0].groupId).toBe(null);
  });

  it('removePerson tar bort personen och dess semestrar', async () => {
    const files = {
      'reg.json': { groups: [], persons: [{ id: 'p1', name: 'Anna', groupId: null }] },
      'vac.json': {
        vacations: [
          { id: 'v1', personId: 'p1', start: '2025-06-01', end: '2025-06-05' },
          { id: 'v2', personId: 'p2', start: '2025-06-01', end: '2025-06-05' },
        ],
      },
    };
    const store = createDataStore({ client: fakeClient(files), ...paths });
    await store.removePerson('p1');
    expect(files['reg.json'].persons).toEqual([]);
    expect(files['vac.json'].vacations.map((v) => v.id)).toEqual(['v2']);
  });
});

describe('dataStore vacations', () => {
  it('lägger till, uppdaterar och tar bort semester', async () => {
    const files = {};
    const store = createDataStore({ client: fakeClient(files), ...paths });
    const added = await store.addVacation('p1', '2025-06-16', '2025-06-20');
    const id = added.vacations[0].id;
    expect(added.vacations[0]).toMatchObject({ personId: 'p1', start: '2025-06-16', end: '2025-06-20' });

    await store.updateVacation(id, '2025-06-17', '2025-06-21');
    expect(files['vac.json'].vacations[0]).toMatchObject({ start: '2025-06-17', end: '2025-06-21' });

    await store.removeVacation(id);
    expect(files['vac.json'].vacations).toEqual([]);
  });
});
```

- [ ] **Step 2: Kör test, verifiera FAIL**

Run: `npm test -- --run tests/dataStore.test.js`
Expected: FAIL — "createDataStore is not a function".

- [ ] **Step 3: Implementera**

`src/lib/dataStore.js`:

```js
const EMPTY_REGISTRY = { groups: [], persons: [] };
const EMPTY_VACATIONS = { vacations: [] };

export function createDataStore({ client, registryPath, vacationsPath }) {
  async function loadRegistry() {
    const { data } = await client.readFile(registryPath);
    return data ?? structuredClone(EMPTY_REGISTRY);
  }

  async function loadVacations() {
    const { data } = await client.readFile(vacationsPath);
    return data ?? structuredClone(EMPTY_VACATIONS);
  }

  function writeRegistry(mutate) {
    return client.writeFile(registryPath, mutate, { emptyValue: EMPTY_REGISTRY });
  }
  function writeVacations(mutate) {
    return client.writeFile(vacationsPath, mutate, { emptyValue: EMPTY_VACATIONS });
  }

  return {
    loadRegistry,
    loadVacations,

    addGroup(name) {
      return writeRegistry((r) => ({
        ...r,
        groups: [...r.groups, { id: crypto.randomUUID(), name }],
      }));
    },

    renameGroup(id, name) {
      return writeRegistry((r) => ({
        ...r,
        groups: r.groups.map((g) => (g.id === id ? { ...g, name } : g)),
      }));
    },

    removeGroup(id) {
      return writeRegistry((r) => ({
        groups: r.groups.filter((g) => g.id !== id),
        persons: r.persons.map((p) => (p.groupId === id ? { ...p, groupId: null } : p)),
      }));
    },

    // Returnerar den skapade personen (stabilt id även om writeFile gör om mutate vid 409).
    async addPerson(name, groupId = null) {
      const person = { id: crypto.randomUUID(), name, groupId };
      await writeRegistry((r) => ({ ...r, persons: [...r.persons, person] }));
      return person;
    },

    setPersonGroup(id, groupId) {
      return writeRegistry((r) => ({
        ...r,
        persons: r.persons.map((p) => (p.id === id ? { ...p, groupId } : p)),
      }));
    },

    async removePerson(id) {
      await writeRegistry((r) => ({ ...r, persons: r.persons.filter((p) => p.id !== id) }));
      return writeVacations((v) => ({ vacations: v.vacations.filter((x) => x.personId !== id) }));
    },

    addVacation(personId, start, end) {
      return writeVacations((v) => ({
        vacations: [...v.vacations, { id: crypto.randomUUID(), personId, start, end }],
      }));
    },

    updateVacation(id, start, end) {
      return writeVacations((v) => ({
        vacations: v.vacations.map((x) => (x.id === id ? { ...x, start, end } : x)),
      }));
    },

    removeVacation(id) {
      return writeVacations((v) => ({ vacations: v.vacations.filter((x) => x.id !== id) }));
    },
  };
}
```

- [ ] **Step 4: Kör test, verifiera PASS**

Run: `npm test -- --run tests/dataStore.test.js`
Expected: alla PASS.

- [ ] **Step 5: Commit**

```bash
git add src/lib/dataStore.js tests/dataStore.test.js
git commit -m "feat: lägg till dataStore med grupp-/person-/semesteroperationer"
```

---

### Task 7: Auth & identitet (`src/lib/auth.js`, `src/lib/identity.js`)

**Files:**
- Create: `src/lib/auth.js`
- Create: `src/lib/identity.js`
- Test: `tests/auth.test.js`
- Test: `tests/identity.test.js`

**Interfaces:**
- Consumes: `localStorage` (jsdom), inget annat.
- Produces:
  - `auth.js`: `isAuthenticated(storage = localStorage): boolean`, `login(password, expected, storage = localStorage): boolean`, `logout(storage = localStorage): void`. Nyckel: `'authenticated'`.
  - `identity.js`: `getPersonId(storage = localStorage): string | null`, `setPersonId(id, storage = localStorage): void`, `resolveIdentity(persons, storage = localStorage): string | null` — returnerar lagrat `personId` om det finns i `persons`, annars rensar nyckeln och returnerar `null`. Nyckel: `'personId'`.

- [ ] **Step 1: Skriv failande test för auth**

`tests/auth.test.js`:

```js
import { describe, it, expect, beforeEach } from 'vitest';
import { isAuthenticated, login, logout } from '../src/lib/auth.js';

describe('auth', () => {
  beforeEach(() => localStorage.clear());

  it('är inte autentiserad från start', () => {
    expect(isAuthenticated()).toBe(false);
  });
  it('login med rätt lösenord sätter flaggan', () => {
    expect(login('hemligt', 'hemligt')).toBe(true);
    expect(isAuthenticated()).toBe(true);
  });
  it('login med fel lösenord misslyckas', () => {
    expect(login('fel', 'hemligt')).toBe(false);
    expect(isAuthenticated()).toBe(false);
  });
  it('logout rensar flaggan', () => {
    login('hemligt', 'hemligt');
    logout();
    expect(isAuthenticated()).toBe(false);
  });
});
```

- [ ] **Step 2: Kör test, verifiera FAIL**

Run: `npm test -- --run tests/auth.test.js`
Expected: FAIL — "isAuthenticated is not a function".

- [ ] **Step 3: Implementera `auth.js`**

`src/lib/auth.js`:

```js
const KEY = 'authenticated';

export function isAuthenticated(storage = localStorage) {
  return storage.getItem(KEY) === 'true';
}

export function login(password, expected, storage = localStorage) {
  if (password === expected) {
    storage.setItem(KEY, 'true');
    return true;
  }
  return false;
}

export function logout(storage = localStorage) {
  storage.removeItem(KEY);
}
```

- [ ] **Step 4: Kör test, verifiera PASS**

Run: `npm test -- --run tests/auth.test.js`
Expected: alla PASS.

- [ ] **Step 5: Skriv failande test för identity**

`tests/identity.test.js`:

```js
import { describe, it, expect, beforeEach } from 'vitest';
import { getPersonId, setPersonId, resolveIdentity } from '../src/lib/identity.js';

describe('identity', () => {
  beforeEach(() => localStorage.clear());

  it('saknar personId från start', () => {
    expect(getPersonId()).toBe(null);
  });
  it('setPersonId lagrar och getPersonId läser', () => {
    setPersonId('p1');
    expect(getPersonId()).toBe('p1');
  });
  it('resolveIdentity returnerar id som finns i registret', () => {
    setPersonId('p1');
    expect(resolveIdentity([{ id: 'p1', name: 'Anna' }])).toBe('p1');
  });
  it('resolveIdentity rensar och returnerar null för okänt id', () => {
    setPersonId('borttagen');
    expect(resolveIdentity([{ id: 'p1', name: 'Anna' }])).toBe(null);
    expect(getPersonId()).toBe(null);
  });
});
```

- [ ] **Step 6: Kör test, verifiera FAIL**

Run: `npm test -- --run tests/identity.test.js`
Expected: FAIL — "getPersonId is not a function".

- [ ] **Step 7: Implementera `identity.js`**

`src/lib/identity.js`:

```js
const KEY = 'personId';

export function getPersonId(storage = localStorage) {
  return storage.getItem(KEY);
}

export function setPersonId(id, storage = localStorage) {
  storage.setItem(KEY, id);
}

export function resolveIdentity(persons, storage = localStorage) {
  const id = getPersonId(storage);
  if (id && persons.some((p) => p.id === id)) return id;
  storage.removeItem(KEY);
  return null;
}
```

- [ ] **Step 8: Kör båda testfilerna, verifiera PASS**

Run: `npm test -- --run tests/auth.test.js tests/identity.test.js`
Expected: alla PASS.

- [ ] **Step 9: Commit**

```bash
git add src/lib/auth.js src/lib/identity.js tests/auth.test.js tests/identity.test.js
git commit -m "feat: lägg till lösenordsauth och person-identitet via localStorage"
```

---

### Task 8: Vacation-hjälpare för kalendern (`src/lib/vacationLookup.js`)

**Files:**
- Create: `src/lib/vacationLookup.js`
- Test: `tests/vacationLookup.test.js`

**Interfaces:**
- Consumes: inget.
- Produces:
  - `vacationForCell(vacations: Vacation[], personId: string, iso: string): Vacation | null`
    — returnerar den semester för personen vars `[start, end]` (inklusivt) täcker `iso`, annars `null`. Används både för cellfärgning och klick-till-redigera (returnerar hela posten även om `iso` ligger vid fönstrets kant).

- [ ] **Step 1: Skriv failande test**

`tests/vacationLookup.test.js`:

```js
import { describe, it, expect } from 'vitest';
import { vacationForCell } from '../src/lib/vacationLookup.js';

const vacations = [
  { id: 'v1', personId: 'p1', start: '2025-06-16', end: '2025-06-20' },
  { id: 'v2', personId: 'p2', start: '2025-06-18', end: '2025-06-18' },
];

describe('vacationForCell', () => {
  it('hittar semester som täcker datumet (inklusiva kanter)', () => {
    expect(vacationForCell(vacations, 'p1', '2025-06-16').id).toBe('v1');
    expect(vacationForCell(vacations, 'p1', '2025-06-20').id).toBe('v1');
    expect(vacationForCell(vacations, 'p1', '2025-06-18').id).toBe('v1');
  });
  it('returnerar null utanför intervallet', () => {
    expect(vacationForCell(vacations, 'p1', '2025-06-21')).toBe(null);
    expect(vacationForCell(vacations, 'p1', '2025-06-15')).toBe(null);
  });
  it('matchar rätt person', () => {
    expect(vacationForCell(vacations, 'p2', '2025-06-18').id).toBe('v2');
    expect(vacationForCell(vacations, 'p2', '2025-06-16')).toBe(null);
  });
  it('returnerar hela posten även när datumet är en kant (för redigering vid fönsterkant)', () => {
    const v = vacationForCell(vacations, 'p1', '2025-06-20');
    expect(v).toMatchObject({ start: '2025-06-16', end: '2025-06-20' });
  });
});
```

- [ ] **Step 2: Kör test, verifiera FAIL**

Run: `npm test -- --run tests/vacationLookup.test.js`
Expected: FAIL — "vacationForCell is not a function".

- [ ] **Step 3: Implementera**

`src/lib/vacationLookup.js`:

```js
// ISO-datumsträngar (YYYY-MM-DD) kan jämföras lexikografiskt.
export function vacationForCell(vacations, personId, iso) {
  return (
    vacations.find(
      (v) => v.personId === personId && v.start <= iso && iso <= v.end,
    ) ?? null
  );
}
```

- [ ] **Step 4: Kör test, verifiera PASS**

Run: `npm test -- --run tests/vacationLookup.test.js`
Expected: alla PASS.

- [ ] **Step 5: Commit**

```bash
git add src/lib/vacationLookup.js tests/vacationLookup.test.js
git commit -m "feat: lägg till semesteruppslag per kalendercell"
```

---

### Task 9: Reaktivt store-composable (`src/composables/useStore.js`)

**Files:**
- Create: `src/composables/useStore.js`
- Test: `tests/useStore.test.js`

**Interfaces:**
- Consumes: `createDataStore` (Task 6). Tar en `dataStore` som argument för testbarhet.
- Produces: `createStore(dataStore)` som returnerar reaktivt objekt:
  - state (refs): `groups`, `persons`, `vacations`, `loading`, `error`
  - `refresh(): Promise<void>` — laddar registry + vacations, fångar fel till `error`.
  - genomskickade actions som anropar dataStore och därefter `refresh()`:
    `addGroup, renameGroup, removeGroup, addPerson, setPersonGroup, removePerson, addVacation, updateVacation, removeVacation`.

- [ ] **Step 1: Skriv failande test**

`tests/useStore.test.js`:

```js
import { describe, it, expect, vi } from 'vitest';
import { createStore } from '../src/composables/useStore.js';

function fakeDataStore() {
  return {
    loadRegistry: vi.fn(async () => ({ groups: [{ id: 'g1', name: 'A' }], persons: [] })),
    loadVacations: vi.fn(async () => ({ vacations: [{ id: 'v1', personId: 'p1', start: '2025-06-01', end: '2025-06-02' }] })),
    addGroup: vi.fn(async () => {}),
  };
}

describe('useStore', () => {
  it('refresh fyller state', async () => {
    const store = createStore(fakeDataStore());
    await store.refresh();
    expect(store.groups.value).toHaveLength(1);
    expect(store.vacations.value).toHaveLength(1);
    expect(store.error.value).toBe(null);
  });

  it('action anropar dataStore, refreshar och returnerar resultatet', async () => {
    const ds = fakeDataStore();
    ds.addGroup = vi.fn(async () => ({ id: 'g99', name: 'B' }));
    const store = createStore(ds);
    const result = await store.addGroup('B');
    expect(ds.addGroup).toHaveBeenCalledWith('B');
    expect(ds.loadRegistry).toHaveBeenCalled();
    expect(result).toEqual({ id: 'g99', name: 'B' });
  });

  it('sätter error vid läsfel', async () => {
    const ds = fakeDataStore();
    ds.loadRegistry = vi.fn(async () => { throw new Error('nätverk'); });
    const store = createStore(ds);
    await store.refresh();
    expect(store.error.value).toBeTruthy();
  });
});
```

- [ ] **Step 2: Kör test, verifiera FAIL**

Run: `npm test -- --run tests/useStore.test.js`
Expected: FAIL — "createStore is not a function".

- [ ] **Step 3: Implementera**

`src/composables/useStore.js`:

```js
import { ref } from 'vue';

export function createStore(dataStore) {
  const groups = ref([]);
  const persons = ref([]);
  const vacations = ref([]);
  const loading = ref(false);
  const error = ref(null);

  async function refresh() {
    loading.value = true;
    error.value = null;
    try {
      const [reg, vac] = await Promise.all([
        dataStore.loadRegistry(),
        dataStore.loadVacations(),
      ]);
      groups.value = reg.groups;
      persons.value = reg.persons;
      vacations.value = vac.vacations;
    } catch (e) {
      error.value = e.message || 'Ett fel uppstod vid laddning.';
    } finally {
      loading.value = false;
    }
  }

  function action(fn) {
    return async (...args) => {
      try {
        const result = await fn(...args);
        await refresh();
        return result;
      } catch (e) {
        error.value = e.message || 'Ett fel uppstod vid sparande.';
        throw e;
      }
    };
  }

  return {
    groups,
    persons,
    vacations,
    loading,
    error,
    refresh,
    addGroup: action(dataStore.addGroup),
    renameGroup: action(dataStore.renameGroup),
    removeGroup: action(dataStore.removeGroup),
    addPerson: action(dataStore.addPerson),
    setPersonGroup: action(dataStore.setPersonGroup),
    removePerson: action(dataStore.removePerson),
    addVacation: action(dataStore.addVacation),
    updateVacation: action(dataStore.updateVacation),
    removeVacation: action(dataStore.removeVacation),
  };
}
```

- [ ] **Step 4: Kör test, verifiera PASS**

Run: `npm test -- --run tests/useStore.test.js`
Expected: alla PASS.

- [ ] **Step 5: Commit**

```bash
git add src/composables/useStore.js tests/useStore.test.js
git commit -m "feat: lägg till reaktivt store-composable"
```

---

### Task 10: Kalendergrid-komponent (`src/components/CalendarGrid.vue`)

**Files:**
- Create: `src/components/CalendarGrid.vue`
- Test: `tests/CalendarGrid.test.js`

**Interfaces:**
- Consumes: `generateColumns` (Task 2), `groupPersons` (Task 4), `vacationForCell` (Task 8).
- Props: `groups: Array`, `persons: Array`, `vacations: Array`, `fromMonth: string`, `toMonth: string`, `groupFilterId: string | null | undefined` (undefined/`'all'` = alla grupper).
- Emits: `edit-vacation` med `{ vacation }` när man klickar på en semestercell.
- Produces: renderad grid med fyra header-rader (månad, vecka, datum+veckodag), sticky vänsterkolumn, helgskuggning, grupp-header-rader.

- [ ] **Step 1: Skriv komponenttest**

`tests/CalendarGrid.test.js`:

```js
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import CalendarGrid from '../src/components/CalendarGrid.vue';

const groups = [{ id: 'g1', name: 'Team A' }];
const persons = [{ id: 'p1', name: 'Anna', groupId: 'g1' }];
const vacations = [{ id: 'v1', personId: 'p1', start: '2025-06-16', end: '2025-06-18' }];

function mountGrid(extra = {}) {
  return mount(CalendarGrid, {
    props: { groups, persons, vacations, fromMonth: '2025-06', toMonth: '2025-06', ...extra },
  });
}

describe('CalendarGrid', () => {
  it('visar gruppnamn och personnamn', () => {
    const w = mountGrid();
    expect(w.text()).toContain('Team A');
    expect(w.text()).toContain('Anna');
  });
  it('renderar en kolumn per dag i månaden', () => {
    const w = mountGrid();
    expect(w.findAll('[data-testid="day-header"]').length).toBe(30);
  });
  it('markerar semesterceller', () => {
    const w = mountGrid();
    expect(w.findAll('[data-vacation="true"]').length).toBe(3);
  });
  it('emittar edit-vacation vid klick på semestercell', async () => {
    const w = mountGrid();
    await w.find('[data-vacation="true"]').trigger('click');
    expect(w.emitted('edit-vacation')[0][0].vacation.id).toBe('v1');
  });
  it('filtrerar på grupp', () => {
    const w = mountGrid({
      groups: [{ id: 'g1', name: 'Team A' }, { id: 'g2', name: 'Team B' }],
      persons: [{ id: 'p1', name: 'Anna', groupId: 'g1' }, { id: 'p2', name: 'Björn', groupId: 'g2' }],
      groupFilterId: 'g2',
    });
    expect(w.text()).toContain('Björn');
    expect(w.text()).not.toContain('Anna');
  });
});
```

- [ ] **Step 2: Kör test, verifiera FAIL**

Run: `npm test -- --run tests/CalendarGrid.test.js`
Expected: FAIL — komponenten finns inte.

- [ ] **Step 3: Implementera komponenten**

`src/components/CalendarGrid.vue`:

```vue
<template>
  <div class="overflow-x-auto border border-base-300 rounded">
    <table class="border-collapse text-sm">
      <thead>
        <!-- Rad 1: månad -->
        <tr>
          <th class="sticky left-0 z-20 bg-base-200 min-w-[12rem] text-left px-2"></th>
          <th
            v-for="m in monthSpans"
            :key="m.monthKey"
            :colspan="m.count"
            class="bg-base-200 px-2 py-1 text-left font-bold whitespace-nowrap border-l border-base-300"
          >
            {{ m.monthLabel }}
          </th>
        </tr>
        <!-- Rad 2: veckonummer -->
        <tr>
          <th class="sticky left-0 z-20 bg-base-200 px-2"></th>
          <th
            v-for="w in weekSpans"
            :key="w.key"
            :colspan="w.count"
            class="bg-base-100 px-1 text-center text-xs font-semibold border-l border-base-300 whitespace-nowrap"
          >
            v.{{ w.isoWeek }}
          </th>
        </tr>
        <!-- Rad 3: datum + veckodag -->
        <tr>
          <th class="sticky left-0 z-20 bg-base-200 px-2 text-left">Person</th>
          <th
            v-for="col in columns"
            :key="col.iso"
            data-testid="day-header"
            class="px-1 text-center align-top w-8"
            :class="col.isWeekend ? 'bg-base-300' : 'bg-base-100'"
          >
            <div class="font-semibold leading-tight">{{ col.day }}</div>
            <div class="text-[10px] opacity-70 leading-tight">{{ col.weekdayLabel }}</div>
          </th>
        </tr>
      </thead>
      <tbody>
        <template v-for="section in sections" :key="section.group.id ?? 'ungrouped'">
          <tr>
            <th
              :colspan="columns.length + 1"
              class="sticky left-0 bg-primary text-primary-content text-left px-2 py-1 font-bold"
            >
              {{ section.group.name }}
            </th>
          </tr>
          <tr v-for="person in section.persons" :key="person.id" class="hover:bg-base-200">
            <td class="sticky left-0 z-10 bg-base-100 px-2 pl-6 whitespace-nowrap">
              {{ person.name }}
            </td>
            <td
              v-for="col in columns"
              :key="col.iso"
              class="border-l border-base-200 h-8 cursor-default"
              :class="cellClass(person.id, col)"
              :data-vacation="isVacation(person.id, col.iso) ? 'true' : 'false'"
              @click="onCellClick(person.id, col.iso)"
            ></td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { generateColumns } from '../lib/dates.js';
import { groupPersons } from '../lib/grouping.js';
import { vacationForCell } from '../lib/vacationLookup.js';

const props = defineProps({
  groups: { type: Array, required: true },
  persons: { type: Array, required: true },
  vacations: { type: Array, required: true },
  fromMonth: { type: String, required: true },
  toMonth: { type: String, required: true },
  groupFilterId: { type: [String, null], default: 'all' },
});

const emit = defineEmits(['edit-vacation']);

const columns = computed(() => generateColumns(props.fromMonth, props.toMonth));

const filteredPersons = computed(() => {
  if (props.groupFilterId === 'all' || props.groupFilterId === undefined) return props.persons;
  return props.persons.filter((p) => p.groupId === props.groupFilterId);
});

const filteredGroups = computed(() => {
  if (props.groupFilterId === 'all' || props.groupFilterId === undefined) return props.groups;
  return props.groups.filter((g) => g.id === props.groupFilterId);
});

const sections = computed(() => groupPersons(filteredGroups.value, filteredPersons.value));

// Sammanhängande månads-spann för colspan.
const monthSpans = computed(() => {
  const spans = [];
  for (const col of columns.value) {
    const last = spans.at(-1);
    if (last && last.monthKey === col.monthKey) last.count++;
    else spans.push({ monthKey: col.monthKey, monthLabel: col.monthLabel, count: 1 });
  }
  return spans;
});

// Sammanhängande vecko-spann för colspan. Jämför på ISO-veckans ISO-år så att
// en vecka som spänner över ett årsskifte inte felaktigt splittras i headern.
const weekSpans = computed(() => {
  const spans = [];
  for (const col of columns.value) {
    const last = spans.at(-1);
    if (last && last.isoWeek === col.isoWeek && last.isoWeekYear === col.isoWeekYear) {
      last.count++;
    } else {
      spans.push({
        key: `${col.isoWeekYear}-${col.isoWeek}`,
        isoWeek: col.isoWeek,
        isoWeekYear: col.isoWeekYear,
        count: 1,
      });
    }
  }
  return spans;
});

function isVacation(personId, iso) {
  return vacationForCell(props.vacations, personId, iso) !== null;
}

function cellClass(personId, col) {
  if (isVacation(personId, col.iso)) return 'bg-success cursor-pointer';
  return col.isWeekend ? 'bg-base-300' : '';
}

function onCellClick(personId, iso) {
  const vacation = vacationForCell(props.vacations, personId, iso);
  if (vacation) emit('edit-vacation', { vacation });
}
</script>
```

- [ ] **Step 4: Kör test, verifiera PASS**

Run: `npm test -- --run tests/CalendarGrid.test.js`
Expected: alla PASS.

- [ ] **Step 5: Commit**

```bash
git add src/components/CalendarGrid.vue tests/CalendarGrid.test.js
git commit -m "feat: lägg till Excel-stil kalendergrid"
```

---

### Task 11: Månadsväljare och gruppfilter (`src/components/CalendarControls.vue`)

**Files:**
- Create: `src/components/CalendarControls.vue`
- Test: `tests/CalendarControls.test.js`

**Interfaces:**
- Consumes: `clampMonthRange` (Task 3).
- Props: `fromMonth: string`, `toMonth: string`, `groups: Array`, `groupFilterId`.
- Emits: `update:range` med `{ from, to }`, `update:groupFilter` med `id | 'all'`, `add-vacation` (knapptryck).
- Produces: två `<input type="month">` med dynamisk 3-månaders-klampning, gruppdropdown, "Lägg till semester"-knapp.

- [ ] **Step 1: Skriv komponenttest**

`tests/CalendarControls.test.js`:

```js
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import CalendarControls from '../src/components/CalendarControls.vue';

const groups = [{ id: 'g1', name: 'Team A' }];

function mountControls(extra = {}) {
  return mount(CalendarControls, {
    props: { fromMonth: '2025-06', toMonth: '2025-08', groups, groupFilterId: 'all', ...extra },
  });
}

describe('CalendarControls', () => {
  it('klampar spannet till 3 månader när to ändras', async () => {
    const w = mountControls();
    const inputs = w.findAll('input[type="month"]');
    await inputs[1].setValue('2025-12');
    const ev = w.emitted('update:range').at(-1)[0];
    expect(ev).toEqual({ from: '2025-10', to: '2025-12' });
  });

  it('emittar gruppfilter', async () => {
    const w = mountControls();
    await w.find('select').setValue('g1');
    expect(w.emitted('update:groupFilter').at(-1)[0]).toBe('g1');
  });

  it('emittar add-vacation', async () => {
    const w = mountControls();
    await w.find('[data-testid="add-vacation"]').trigger('click');
    expect(w.emitted('add-vacation')).toBeTruthy();
  });
});
```

- [ ] **Step 2: Kör test, verifiera FAIL**

Run: `npm test -- --run tests/CalendarControls.test.js`
Expected: FAIL — komponenten finns inte.

- [ ] **Step 3: Implementera**

`src/components/CalendarControls.vue`:

```vue
<template>
  <div class="flex flex-wrap items-end gap-3 mb-4">
    <label class="form-control">
      <span class="label-text">Från månad</span>
      <input
        type="month"
        class="input input-bordered input-sm"
        :value="fromMonth"
        @change="onRangeChange('from', $event.target.value)"
      />
    </label>
    <label class="form-control">
      <span class="label-text">Till månad</span>
      <input
        type="month"
        class="input input-bordered input-sm"
        :value="toMonth"
        @change="onRangeChange('to', $event.target.value)"
      />
    </label>
    <label class="form-control">
      <span class="label-text">Grupp</span>
      <select
        class="select select-bordered select-sm"
        :value="groupFilterId"
        @change="$emit('update:groupFilter', $event.target.value)"
      >
        <option value="all">Alla grupper</option>
        <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
      </select>
    </label>
    <button data-testid="add-vacation" class="btn btn-primary btn-sm ml-auto" @click="$emit('add-vacation')">
      <span class="mdi mdi-plus"></span> Lägg till semester
    </button>
  </div>
</template>

<script setup>
import { clampMonthRange } from '../lib/validation.js';

const props = defineProps({
  fromMonth: { type: String, required: true },
  toMonth: { type: String, required: true },
  groups: { type: Array, required: true },
  groupFilterId: { type: [String, null], default: 'all' },
});

const emit = defineEmits(['update:range', 'update:groupFilter', 'add-vacation']);

function onRangeChange(changed, value) {
  const from = changed === 'from' ? value : props.fromMonth;
  const to = changed === 'to' ? value : props.toMonth;
  emit('update:range', clampMonthRange(from, to, changed));
}
</script>
```

- [ ] **Step 4: Kör test, verifiera PASS**

Run: `npm test -- --run tests/CalendarControls.test.js`
Expected: alla PASS.

- [ ] **Step 5: Commit**

```bash
git add src/components/CalendarControls.vue tests/CalendarControls.test.js
git commit -m "feat: lägg till månadsväljare med 3-mån-klamp och gruppfilter"
```

---

### Task 12: Semester-modal (`src/components/VacationModal.vue`)

**Files:**
- Create: `src/components/VacationModal.vue`
- Test: `tests/VacationModal.test.js`

**Interfaces:**
- Consumes: `isValidVacationRange` (Task 3).
- Props: `modelValue: boolean` (öppen/stängd), `vacation: object | null` (null = ny), `personName: string`.
- Emits: `save` med `{ start, end }`, `delete` med `{ id }`, `update:modelValue` (stäng).
- Produces: modal med start/slut-datumfält, valideringsmeddelande, Spara-knapp (avstängd vid ogiltigt intervall), Ta bort-knapp (endast vid redigering).

- [ ] **Step 1: Skriv komponenttest**

`tests/VacationModal.test.js`:

```js
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import VacationModal from '../src/components/VacationModal.vue';

function mountModal(extra = {}) {
  return mount(VacationModal, {
    props: { modelValue: true, vacation: null, personName: 'Anna', ...extra },
  });
}

describe('VacationModal', () => {
  it('inaktiverar Spara vid ogiltigt intervall', async () => {
    const w = mountModal();
    await w.find('[data-testid="start"]').setValue('2025-06-20');
    await w.find('[data-testid="end"]').setValue('2025-06-16');
    expect(w.find('[data-testid="save"]').attributes('disabled')).toBeDefined();
  });

  it('emittar save med giltigt intervall', async () => {
    const w = mountModal();
    await w.find('[data-testid="start"]').setValue('2025-06-16');
    await w.find('[data-testid="end"]').setValue('2025-06-20');
    await w.find('[data-testid="save"]').trigger('click');
    expect(w.emitted('save')[0][0]).toEqual({ start: '2025-06-16', end: '2025-06-20' });
  });

  it('visar Ta bort endast vid redigering och emittar delete', async () => {
    const w = mountModal({ vacation: { id: 'v1', personId: 'p1', start: '2025-06-16', end: '2025-06-20' } });
    const del = w.find('[data-testid="delete"]');
    expect(del.exists()).toBe(true);
    await del.trigger('click');
    expect(w.emitted('delete')[0][0]).toEqual({ id: 'v1' });
  });

  it('döljer Ta bort vid ny semester', () => {
    const w = mountModal({ vacation: null });
    expect(w.find('[data-testid="delete"]').exists()).toBe(false);
  });
});
```

- [ ] **Step 2: Kör test, verifiera FAIL**

Run: `npm test -- --run tests/VacationModal.test.js`
Expected: FAIL — komponenten finns inte.

- [ ] **Step 3: Implementera**

`src/components/VacationModal.vue`:

```vue
<template>
  <dialog class="modal" :open="modelValue">
    <div class="modal-box">
      <h3 class="font-bold text-lg mb-2">
        {{ vacation ? 'Redigera semester' : 'Lägg till semester' }} — {{ personName }}
      </h3>
      <div class="flex flex-col gap-2">
        <label class="form-control">
          <span class="label-text">Startdatum</span>
          <input data-testid="start" type="date" class="input input-bordered" v-model="start" />
        </label>
        <label class="form-control">
          <span class="label-text">Slutdatum</span>
          <input data-testid="end" type="date" class="input input-bordered" v-model="end" />
        </label>
        <p v-if="!isValid" class="text-error text-sm">
          Slutdatum måste vara samma som eller efter startdatum.
        </p>
      </div>
      <div class="modal-action">
        <button
          v-if="vacation"
          data-testid="delete"
          class="btn btn-error mr-auto"
          @click="$emit('delete', { id: vacation.id })"
        >
          Ta bort
        </button>
        <button class="btn" @click="$emit('update:modelValue', false)">Avbryt</button>
        <button
          data-testid="save"
          class="btn btn-primary"
          :disabled="!isValid"
          @click="$emit('save', { start, end })"
        >
          Spara
        </button>
      </div>
    </div>
  </dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { isValidVacationRange } from '../lib/validation.js';

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  vacation: { type: Object, default: null },
  personName: { type: String, default: '' },
});

defineEmits(['save', 'delete', 'update:modelValue']);

const start = ref(props.vacation?.start ?? '');
const end = ref(props.vacation?.end ?? '');

// Synka fälten när en annan semester öppnas.
watch(
  () => props.vacation,
  (v) => {
    start.value = v?.start ?? '';
    end.value = v?.end ?? '';
  },
);

const isValid = computed(() => isValidVacationRange(start.value, end.value));
</script>
```

- [ ] **Step 4: Kör test, verifiera PASS**

Run: `npm test -- --run tests/VacationModal.test.js`
Expected: alla PASS.

- [ ] **Step 5: Commit**

```bash
git add src/components/VacationModal.vue tests/VacationModal.test.js
git commit -m "feat: lägg till semester-modal med validering"
```

---

### Task 13: Login- och personväljarvyer (`LoginView.vue`, `PersonSelectView.vue`)

**Files:**
- Create: `src/components/LoginView.vue`
- Create: `src/components/PersonSelectView.vue`
- Test: `tests/LoginView.test.js`
- Test: `tests/PersonSelectView.test.js`

**Interfaces:**
- `LoginView` — Emits: `submit` med lösenordssträng. Props: `error: boolean`.
- `PersonSelectView` — Props: `persons: Array`. Emits: `select` med `personId`, `create` med `name`.

- [ ] **Step 1: Skriv komponenttester**

`tests/LoginView.test.js`:

```js
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import LoginView from '../src/components/LoginView.vue';

describe('LoginView', () => {
  it('emittar submit med inskrivet lösenord', async () => {
    const w = mount(LoginView, { props: { error: false } });
    await w.find('input[type="password"]').setValue('hemligt');
    await w.find('form').trigger('submit.prevent');
    expect(w.emitted('submit')[0][0]).toBe('hemligt');
  });
  it('visar felmeddelande när error är sant', () => {
    const w = mount(LoginView, { props: { error: true } });
    expect(w.text()).toContain('Fel lösenord');
  });
});
```

`tests/PersonSelectView.test.js`:

```js
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PersonSelectView from '../src/components/PersonSelectView.vue';

const persons = [{ id: 'p1', name: 'Anna', groupId: null }];

describe('PersonSelectView', () => {
  it('emittar select med valt id', async () => {
    const w = mount(PersonSelectView, { props: { persons } });
    await w.find('[data-testid="select-p1"]').trigger('click');
    expect(w.emitted('select')[0][0]).toBe('p1');
  });
  it('emittar create med nytt namn', async () => {
    const w = mount(PersonSelectView, { props: { persons } });
    await w.find('[data-testid="new-name"]').setValue('Björn');
    await w.find('[data-testid="create"]').trigger('click');
    expect(w.emitted('create')[0][0]).toBe('Björn');
  });
});
```

- [ ] **Step 2: Kör tester, verifiera FAIL**

Run: `npm test -- --run tests/LoginView.test.js tests/PersonSelectView.test.js`
Expected: FAIL — komponenterna finns inte.

- [ ] **Step 3: Implementera `LoginView.vue`**

`src/components/LoginView.vue`:

```vue
<template>
  <div class="min-h-screen flex items-center justify-center bg-base-200">
    <form class="card bg-base-100 shadow-md p-6 w-80" @submit.prevent="$emit('submit', password)">
      <h1 class="text-xl font-bold mb-4">Logga in</h1>
      <input
        type="password"
        class="input input-bordered w-full mb-2"
        placeholder="Lösenord"
        v-model="password"
      />
      <p v-if="error" class="text-error text-sm mb-2">Fel lösenord. Försök igen.</p>
      <button type="submit" class="btn btn-primary w-full">Logga in</button>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue';
defineProps({ error: { type: Boolean, default: false } });
defineEmits(['submit']);
const password = ref('');
</script>
```

- [ ] **Step 4: Implementera `PersonSelectView.vue`**

`src/components/PersonSelectView.vue`:

```vue
<template>
  <div class="min-h-screen flex items-center justify-center bg-base-200">
    <div class="card bg-base-100 shadow-md p-6 w-96">
      <h1 class="text-xl font-bold mb-4">Vem är du?</h1>

      <div v-if="persons.length" class="mb-4">
        <p class="label-text mb-1">Välj befintlig:</p>
        <div class="flex flex-col gap-1 max-h-60 overflow-y-auto">
          <button
            v-for="p in persons"
            :key="p.id"
            :data-testid="`select-${p.id}`"
            class="btn btn-ghost justify-start"
            @click="$emit('select', p.id)"
          >
            {{ p.name }}
          </button>
        </div>
      </div>

      <div class="divider">eller</div>

      <p class="label-text mb-1">Skapa ny:</p>
      <div class="flex gap-2">
        <input
          data-testid="new-name"
          class="input input-bordered flex-1"
          placeholder="Ditt namn"
          v-model="newName"
        />
        <button data-testid="create" class="btn btn-primary" :disabled="!newName.trim()" @click="$emit('create', newName.trim())">
          Skapa
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
defineProps({ persons: { type: Array, required: true } });
defineEmits(['select', 'create']);
const newName = ref('');
</script>
```

- [ ] **Step 5: Kör tester, verifiera PASS**

Run: `npm test -- --run tests/LoginView.test.js tests/PersonSelectView.test.js`
Expected: alla PASS.

- [ ] **Step 6: Commit**

```bash
git add src/components/LoginView.vue src/components/PersonSelectView.vue tests/LoginView.test.js tests/PersonSelectView.test.js
git commit -m "feat: lägg till login- och personväljarvyer"
```

---

### Task 14: Admin-panel (`src/components/AdminPanel.vue`)

**Files:**
- Create: `src/components/AdminPanel.vue`
- Test: `tests/AdminPanel.test.js`

**Interfaces:**
- Props: `groups: Array`, `persons: Array`.
- Emits: `add-group` (name), `rename-group` ({ id, name }), `remove-group` (id), `add-person` ({ name, groupId }), `set-person-group` ({ id, groupId }), `remove-person` (id).
- Produces: inbyggd panel (daisyUI `collapse`) i huvudvyn för grupp-/personhantering. Borttagning av person sker via `confirm()`-dialog (öppen modell, varnar om semestrar tas bort).

- [ ] **Step 1: Skriv komponenttest**

`tests/AdminPanel.test.js`:

```js
import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import AdminPanel from '../src/components/AdminPanel.vue';

const groups = [{ id: 'g1', name: 'Team A' }];
const persons = [{ id: 'p1', name: 'Anna', groupId: 'g1' }];

describe('AdminPanel', () => {
  it('emittar add-group', async () => {
    const w = mount(AdminPanel, { props: { groups, persons } });
    await w.find('[data-testid="new-group"]').setValue('Team B');
    await w.find('[data-testid="add-group"]').trigger('click');
    expect(w.emitted('add-group')[0][0]).toBe('Team B');
  });

  it('emittar add-person med vald grupp', async () => {
    const w = mount(AdminPanel, { props: { groups, persons } });
    await w.find('[data-testid="new-person"]').setValue('Björn');
    await w.find('[data-testid="new-person-group"]').setValue('g1');
    await w.find('[data-testid="add-person"]').trigger('click');
    expect(w.emitted('add-person')[0][0]).toEqual({ name: 'Björn', groupId: 'g1' });
  });

  it('bekräftar och emittar remove-person', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    const w = mount(AdminPanel, { props: { groups, persons } });
    await w.find('[data-testid="remove-person-p1"]').trigger('click');
    expect(w.emitted('remove-person')[0][0]).toBe('p1');
    window.confirm.mockRestore();
  });

  it('avbryter remove-person när confirm avvisas', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false);
    const w = mount(AdminPanel, { props: { groups, persons } });
    await w.find('[data-testid="remove-person-p1"]').trigger('click');
    expect(w.emitted('remove-person')).toBeFalsy();
    window.confirm.mockRestore();
  });
});
```

- [ ] **Step 2: Kör test, verifiera FAIL**

Run: `npm test -- --run tests/AdminPanel.test.js`
Expected: FAIL — komponenten finns inte.

- [ ] **Step 3: Implementera**

`src/components/AdminPanel.vue`:

```vue
<template>
  <div class="collapse collapse-arrow border border-base-300 rounded mb-4">
    <input type="checkbox" />
    <div class="collapse-title font-semibold">Hantera personer & grupper</div>
    <div class="collapse-content">
      <div class="grid md:grid-cols-2 gap-6">
        <!-- Grupper -->
        <div>
          <h3 class="font-bold mb-2">Grupper</h3>
          <ul class="flex flex-col gap-1 mb-3">
            <li v-for="g in groups" :key="g.id" class="flex items-center gap-2">
              <input
                class="input input-bordered input-sm flex-1"
                :value="g.name"
                @change="$emit('rename-group', { id: g.id, name: $event.target.value })"
              />
              <button class="btn btn-ghost btn-sm" @click="$emit('remove-group', g.id)">
                <span class="mdi mdi-delete"></span>
              </button>
            </li>
          </ul>
          <div class="flex gap-2">
            <input data-testid="new-group" class="input input-bordered input-sm flex-1" placeholder="Ny grupp" v-model="newGroup" />
            <button data-testid="add-group" class="btn btn-primary btn-sm" :disabled="!newGroup.trim()" @click="addGroup">
              Lägg till
            </button>
          </div>
        </div>

        <!-- Personer -->
        <div>
          <h3 class="font-bold mb-2">Personer</h3>
          <ul class="flex flex-col gap-1 mb-3">
            <li v-for="p in persons" :key="p.id" class="flex items-center gap-2">
              <span class="flex-1">{{ p.name }}</span>
              <select
                class="select select-bordered select-sm"
                :value="p.groupId ?? ''"
                @change="$emit('set-person-group', { id: p.id, groupId: $event.target.value || null })"
              >
                <option value="">Ogrupperade</option>
                <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
              </select>
              <button
                :data-testid="`remove-person-${p.id}`"
                class="btn btn-ghost btn-sm"
                @click="removePerson(p.id)"
              >
                <span class="mdi mdi-delete"></span>
              </button>
            </li>
          </ul>
          <div class="flex gap-2">
            <input data-testid="new-person" class="input input-bordered input-sm flex-1" placeholder="Ny person" v-model="newPerson" />
            <select data-testid="new-person-group" class="select select-bordered select-sm" v-model="newPersonGroup">
              <option value="">Ogrupperade</option>
              <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>
            <button data-testid="add-person" class="btn btn-primary btn-sm" :disabled="!newPerson.trim()" @click="addPerson">
              Lägg till
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({ groups: { type: Array, required: true }, persons: { type: Array, required: true } });
const emit = defineEmits([
  'add-group', 'rename-group', 'remove-group',
  'add-person', 'set-person-group', 'remove-person',
]);

const newGroup = ref('');
const newPerson = ref('');
const newPersonGroup = ref('');

function addGroup() {
  emit('add-group', newGroup.value.trim());
  newGroup.value = '';
}
function addPerson() {
  emit('add-person', { name: newPerson.value.trim(), groupId: newPersonGroup.value || null });
  newPerson.value = '';
  newPersonGroup.value = '';
}
function removePerson(id) {
  if (confirm('Ta bort personen och alla dess registrerade semestrar?')) {
    emit('remove-person', id);
  }
}
</script>
```

- [ ] **Step 4: Kör test, verifiera PASS**

Run: `npm test -- --run tests/AdminPanel.test.js`
Expected: alla PASS.

- [ ] **Step 5: Commit**

```bash
git add src/components/AdminPanel.vue tests/AdminPanel.test.js
git commit -m "feat: lägg till inbyggd admin-panel för personer och grupper"
```

---

### Task 15: App-orkestrering (`src/App.vue`)

**Files:**
- Modify: `src/App.vue`
- Create: `src/composables/useDefaultRange.js`
- Test: `tests/useDefaultRange.test.js`

**Interfaces:**
- Consumes: alla tidigare moduler + `config` (Task 1).
- `useDefaultRange.js` producerar: `defaultRange(today: Date): { from: string, to: string }` — innevarande månad + två framåt (3 mån totalt).
- `App.vue` knyter ihop: auth-gate → person-gate → huvudvy (controls + admin + grid + modal + felbanner).

- [ ] **Step 1: Skriv failande test för `defaultRange`**

`tests/useDefaultRange.test.js`:

```js
import { describe, it, expect } from 'vitest';
import { defaultRange } from '../src/composables/useDefaultRange.js';

describe('defaultRange', () => {
  it('ger innevarande månad + två framåt', () => {
    expect(defaultRange(new Date(2025, 5, 18))).toEqual({ from: '2025-06', to: '2025-08' });
  });
  it('hanterar årsskifte', () => {
    expect(defaultRange(new Date(2025, 10, 1))).toEqual({ from: '2025-11', to: '2026-01' });
  });
});
```

- [ ] **Step 2: Kör test, verifiera FAIL**

Run: `npm test -- --run tests/useDefaultRange.test.js`
Expected: FAIL — "defaultRange is not a function".

- [ ] **Step 3: Implementera `defaultRange`**

`src/composables/useDefaultRange.js`:

```js
function formatMonth(year, monthIndexZeroBased) {
  const y = year + Math.floor(monthIndexZeroBased / 12);
  const m = ((monthIndexZeroBased % 12) + 12) % 12;
  return `${y}-${String(m + 1).padStart(2, '0')}`;
}

export function defaultRange(today) {
  const year = today.getFullYear();
  const month = today.getMonth();
  return { from: formatMonth(year, month), to: formatMonth(year, month + 2) };
}
```

- [ ] **Step 4: Kör test, verifiera PASS**

Run: `npm test -- --run tests/useDefaultRange.test.js`
Expected: PASS.

- [ ] **Step 5: Skriv om `src/App.vue` (orkestrering)**

`src/App.vue`:

```vue
<template>
  <!-- Steg 1: inloggning -->
  <LoginView v-if="!authed" :error="loginError" @submit="onLogin" />

  <!-- Steg 2: välj person -->
  <PersonSelectView
    v-else-if="!myPersonId"
    :persons="store.persons.value"
    @select="onSelectPerson"
    @create="onCreatePerson"
  />

  <!-- Steg 3: huvudvy -->
  <div v-else class="p-4 max-w-full">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold">Semesterkalender</h1>
      <span class="text-sm opacity-70">Inloggad som {{ myName }}</span>
    </div>

    <div v-if="store.error.value" class="alert alert-error mb-4">
      <span>{{ store.error.value }}</span>
      <button class="btn btn-sm" @click="store.refresh()">Försök igen</button>
    </div>

    <AdminPanel
      :groups="store.groups.value"
      :persons="store.persons.value"
      @add-group="store.addGroup"
      @rename-group="(e) => store.renameGroup(e.id, e.name)"
      @remove-group="store.removeGroup"
      @add-person="(e) => store.addPerson(e.name, e.groupId)"
      @set-person-group="(e) => store.setPersonGroup(e.id, e.groupId)"
      @remove-person="onRemovePerson"
    />

    <CalendarControls
      :from-month="range.from"
      :to-month="range.to"
      :groups="store.groups.value"
      :group-filter-id="groupFilterId"
      @update:range="range = $event"
      @update:groupFilter="groupFilterId = $event"
      @add-vacation="openNewVacation"
    />

    <CalendarGrid
      :groups="store.groups.value"
      :persons="store.persons.value"
      :vacations="store.vacations.value"
      :from-month="range.from"
      :to-month="range.to"
      :group-filter-id="groupFilterId"
      @edit-vacation="openEditVacation"
    />

    <VacationModal
      v-model="modalOpen"
      :vacation="editing"
      :person-name="myName"
      @save="onSaveVacation"
      @delete="onDeleteVacation"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import config from './config.js';
import { createGithubClient } from './lib/githubClient.js';
import { createDataStore } from './lib/dataStore.js';
import { createStore } from './composables/useStore.js';
import { defaultRange } from './composables/useDefaultRange.js';
import { isAuthenticated, login } from './lib/auth.js';
import { getPersonId, setPersonId, resolveIdentity } from './lib/identity.js';
import LoginView from './components/LoginView.vue';
import PersonSelectView from './components/PersonSelectView.vue';
import AdminPanel from './components/AdminPanel.vue';
import CalendarControls from './components/CalendarControls.vue';
import CalendarGrid from './components/CalendarGrid.vue';
import VacationModal from './components/VacationModal.vue';

const client = createGithubClient({
  owner: config.github.owner,
  repo: config.github.repo,
  branch: config.github.branch,
  token: config.github.token,
});
const dataStore = createDataStore({
  client,
  registryPath: config.github.registryPath,
  vacationsPath: config.github.vacationsPath,
});
const store = createStore(dataStore);

const authed = ref(isAuthenticated());
const loginError = ref(false);
const myPersonId = ref(null);

const range = ref(defaultRange(new Date()));
const groupFilterId = ref('all');

const modalOpen = ref(false);
const editing = ref(null);

const myName = computed(
  () => store.persons.value.find((p) => p.id === myPersonId.value)?.name ?? '',
);

onMounted(async () => {
  if (authed.value) await afterAuth();
});

async function onLogin(password) {
  if (login(password, config.password)) {
    authed.value = true;
    loginError.value = false;
    await afterAuth();
  } else {
    loginError.value = true;
  }
}

async function afterAuth() {
  await store.refresh();
  myPersonId.value = resolveIdentity(store.persons.value);
}

function onSelectPerson(id) {
  setPersonId(id);
  myPersonId.value = id;
}

async function onCreatePerson(name) {
  const created = await store.addPerson(name, null);
  setPersonId(created.id);
  myPersonId.value = created.id;
}

async function onRemovePerson(id) {
  await store.removePerson(id);
  if (id === myPersonId.value) myPersonId.value = resolveIdentity(store.persons.value);
}

function openNewVacation() {
  editing.value = null;
  modalOpen.value = true;
}
function openEditVacation({ vacation }) {
  editing.value = vacation;
  modalOpen.value = true;
}

async function onSaveVacation({ start, end }) {
  if (editing.value) await store.updateVacation(editing.value.id, start, end);
  else await store.addVacation(myPersonId.value, start, end);
  modalOpen.value = false;
}

async function onDeleteVacation({ id }) {
  await store.removeVacation(id);
  modalOpen.value = false;
}
</script>
```

- [ ] **Step 6: Uppdatera smoke-test för det nya App-skalet**

Ersätt `tests/smoke.test.js`:

```js
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import App from '../src/App.vue';

describe('App', () => {
  it('visar inloggningsvyn när man inte är autentiserad', () => {
    localStorage.clear();
    const wrapper = mount(App);
    expect(wrapper.text()).toContain('Logga in');
  });
});
```

- [ ] **Step 7: Kör hela testsviten, verifiera PASS**

Run: `npm test -- --run`
Expected: alla testfiler PASS.

- [ ] **Step 8: Verifiera bygge**

Run: `npm run build`
Expected: bygge lyckas utan fel.

- [ ] **Step 9: Commit**

```bash
git add src/App.vue src/composables/useDefaultRange.js tests/useDefaultRange.test.js tests/smoke.test.js
git commit -m "feat: knyt ihop appen med auth-, person- och kalenderflöden"
```

---

### Task 16: Datafiler, dokumentation och deploy-verifiering

**Files:**
- Create: `data/registry.json`
- Create: `data/vacations.json`
- Modify: `README.md`

**Interfaces:**
- Consumes: hela appen.
- Produces: initiala (tomma) datafiler i repot, uppdaterad README.

- [ ] **Step 1: Skapa initiala datafiler**

`data/registry.json`:

```json
{
  "groups": [],
  "persons": []
}
```

`data/vacations.json`:

```json
{
  "vacations": []
}
```

- [ ] **Step 2: Uppdatera README med körinstruktioner**

Lägg till ett "Datafiler"-avsnitt i `README.md` som beskriver att `data/registry.json`
och `data/vacations.json` skrivs av appen via GitHub API och att `VITE_*`-variablerna
i `.env` måste peka på rätt repo/branch/sökvägar.

- [ ] **Step 3: Kör hela sviten en sista gång**

Run: `npm test -- --run`
Expected: alla PASS.

- [ ] **Step 4: Verifiera produktionsbygge**

Run: `npm run build`
Expected: bygge lyckas; `dist/` innehåller `index.html` med base `/vactionnode/`.

- [ ] **Step 5: Commit**

```bash
git add data/registry.json data/vacations.json README.md
git commit -m "feat: lägg till initiala datafiler och uppdatera dokumentation"
```

- [ ] **Step 6: Manuell deploy (utförs av Rickard när .env är ifylld)**

Detta steg körs manuellt av användaren, inte av en agent:

```bash
# .env måste vara ifylld med VITE_PASSWORD + VITE_GITHUB_* först
npm run deploy
```

Aktivera sedan GitHub Pages i repo-inställningarna (branch `gh-pages`, root).

---

## Self-Review

**1. Spec coverage:**

| Spec-avsnitt | Task |
|---|---|
| §2 Stack (Vue/Vite/Tailwind/daisyUI/@mdi/font/Vitest) | Task 1 |
| §3 Datamodell, två filer, UUID, Ogrupperade | Task 4, 6 |
| §4 Konfiguration (VITE_*-variabler) | Task 1 (`config.js`) |
| §4 Inloggningsflöde (lösenord, localStorage) | Task 7, 15 |
| §4 Person-flöde (skapa/välj, validera mot registret) | Task 7, 13, 15 |
| §5 Layout (4 header-rader, sticky, grupp-headers) | Task 10 |
| §5 Helgfärg, block klipps vid fönsterkant | Task 8, 10 |
| §5 ISO-veckor, måndag-start | Task 2 |
| §5 Max 3 mån, dynamisk klamp | Task 3, 11 |
| §5 Gruppfilter | Task 10, 11 |
| §6 Lägg till semester (intervall, validering) | Task 3, 12 |
| §6 Redigera/ta bort (allas) | Task 8, 12, 15 |
| §6 Hantera personer/grupper inbyggt | Task 14 |
| §6 Ta bort person → ta bort semestrar | Task 6 |
| §6 Ta bort grupp → nulla groupId | Task 6 |
| §7 GitHub API-lager, läs/skriv, retry, last-write-wins | Task 5 |
| §8 Felhantering (409/nätverk/403/korrupt) | Task 5, 9, 15 |
| §9 Testning (alla logikpunkter) | Task 2, 3, 4, 8 |
| §4 Deploy (gh-pages manuellt) | Task 1, 16 |

Alla spec-avsnitt har minst en task. Inga luckor.

**2. Placeholder scan:** Inga TBD/TODO/"handle edge cases" — all kod är komplett. README-uppdraget i Task 16 Step 2 beskriver konkret innehåll.

**3. Type consistency:** `createGithubClient`/`createDataStore`/`createStore`-signaturer matchar mellan tasks. `vacationForCell`, `groupPersons`, `clampMonthRange`, `generateColumns` används med samma signaturer i komponenterna som de definieras med. Event-namn (`edit-vacation`, `update:range`, `update:groupFilter`, `add-vacation`, `save`, `delete`) är konsekventa mellan komponent och `App.vue`.
