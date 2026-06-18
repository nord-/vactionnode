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
