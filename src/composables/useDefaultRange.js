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
