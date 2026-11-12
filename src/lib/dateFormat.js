// Datum visas och tolkas i användarens språk (webbläsarens "Web site
// language"), inte i webbläsarens visningsspråk som <input type="date">
// följer. Internt är allt ISO: 'YYYY-MM-DD' för datum, 'YYYY-MM' för månad.
//
// Alla Intl-anrop körs i UTC så att datumet aldrig glider en dag på grund av
// lokal tidszon, med latinska siffror så att tolkningen alltid kan läsa
// tillbaka det som formateringen skrev, och i den gregorianska kalendern: th-TH
// skriver annars buddhistiska år (2569) och fa-IR persiska (1405), som sedan
// hade sparats som om de vore gregorianska.

const BASE = { timeZone: 'UTC', numberingSystem: 'latn', calendar: 'gregory' };
const ISO_DATE = /^(\d{4})-(\d{1,2})-(\d{1,2})$/;

// Datumet som ett UTC-Date, eller null om det inte finns (t.ex. 30 feb).
function toUtcDate(year, month, day) {
  const date = new Date(Date.UTC(year, month - 1, day));
  const exists = date.getUTCFullYear() === year
    && date.getUTCMonth() === month - 1
    && date.getUTCDate() === day;
  return exists ? date : null;
}

function toIso(year, month, day) {
  return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

// Ordningen på år, månad och dag i språkets korta datumformat,
// t.ex. ['year', 'month', 'day'] för sv-SE och ['month', 'day', 'year'] för en-US.
function dateOrder(locale) {
  return sampleParts(locale)
    .map((p) => p.type)
    .filter((t) => t === 'year' || t === 'month' || t === 'day');
}

function sampleParts(locale) {
  return new Intl.DateTimeFormat(locale, BASE).formatToParts(new Date(Date.UTC(2026, 5, 16)));
}

// Riktningstecken bort och alla sorters blanksteg till ett vanligt: ICU ger
// U+202F i formatToParts där format() ger ett vanligt blanksteg (bg-BG).
function normalizeSpacing(text) {
  return text.replace(/[‎‏]/g, '').replace(/\s+/g, ' ');
}

// Språkets egna skiljetecken, längsta först, t.ex. ' г.' för bg-BG. De byts
// mot blanksteg innan texten tolkas, så att språket kan läsa tillbaka sitt
// eget format trots bokstäver och riktningstecken.
function literals(locale) {
  return sampleParts(locale)
    .filter((p) => p.type === 'literal')
    .map((p) => normalizeSpacing(p.value))
    .filter((value) => value.trim() !== '')
    .sort((a, b) => b.length - a.length);
}

export function formatDate(iso, locale) {
  const m = ISO_DATE.exec(iso ?? '');
  const date = m && toUtcDate(Number(m[1]), Number(m[2]), Number(m[3]));
  return date ? new Intl.DateTimeFormat(locale, BASE).format(date) : '';
}

// Tolkar text skriven i språkets ordning och returnerar ISO-datum, eller null.
// Skiljetecknen spelar ingen roll, bara att det finns tre siffergrupper med
// ett fyrsiffrigt år. ISO-format godtas alltid, oavsett språk.
export function parseDate(text, locale) {
  const trimmed = (text ?? '').trim();

  let year;
  let month;
  let day;
  const iso = ISO_DATE.exec(trimmed);
  if (iso) {
    [year, month, day] = iso.slice(1);
  } else {
    const cleaned = literals(locale)
      .reduce((t, lit) => t.split(lit).join(' '), normalizeSpacing(trimmed));
    if (/[^\d\s./-]/.test(cleaned)) return null;
    const groups = cleaned.match(/\d+/g) ?? [];
    if (groups.length !== 3) return null;
    const values = {};
    dateOrder(locale).forEach((type, i) => {
      values[type] = groups[i];
    });
    ({ year, month, day } = values);
  }

  if (year?.length !== 4) return null;
  const date = toUtcDate(Number(year), Number(month), Number(day));
  return date ? toIso(Number(year), Number(month), Number(day)) : null;
}

// Månadernas namn på språket, januari först.
export function monthNames(locale) {
  const format = new Intl.DateTimeFormat(locale, { ...BASE, month: 'long' });
  return Array.from({ length: 12 }, (_, i) => format.format(new Date(Date.UTC(2026, i, 1))));
}

// Om språket skriver månaden före året ("juni 2026") eller efter ("2026年6月").
export function monthYearOrder(locale) {
  return new Intl.DateTimeFormat(locale, { ...BASE, year: 'numeric', month: 'long' })
    .formatToParts(new Date(Date.UTC(2026, 5, 1)))
    .map((p) => p.type)
    .filter((t) => t === 'year' || t === 'month');
}
