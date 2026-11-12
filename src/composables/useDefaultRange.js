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

// defaultRange ger månader; API:et vill ha datum. Sista dagen i to-månaden
// räknas ut med dag 0 i nästa månad, vilket hanterar både 28, 29, 30 och 31.
export function monthRangeToDates({ from, to }) {
  const [year, month] = to.split('-').map(Number);
  const lastDay = new Date(Date.UTC(year, month, 0)).getUTCDate();

  return { from: `${from}-01`, to: `${to}-${String(lastDay).padStart(2, '0')}` };
}
