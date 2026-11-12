import { weekAlignedRange } from '../lib/dates.js';

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

// defaultRange ger månader; API:et vill ha datum. Kalendern visar hela
// veckor, så intervallet fylls ut på samma sätt som kolumnerna: annars syns
// inte semester som ligger på utfyllnadsdagarna före och efter månaderna.
export function monthRangeToDates({ from, to }) {
  return weekAlignedRange(from, to);
}
