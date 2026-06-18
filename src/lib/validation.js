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
