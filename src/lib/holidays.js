// Slår upp helgdagsnamn per ISO-datum.
export function holidayMap(holidays) {
  return new Map(holidays.map((h) => [h.date, h.name]));
}
