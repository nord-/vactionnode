// ISO-datumsträngar (YYYY-MM-DD) kan jämföras lexikografiskt.
export function vacationForCell(vacations, personId, iso) {
  return (
    vacations.find(
      (v) => v.personId === personId && v.start <= iso && iso <= v.end,
    ) ?? null
  );
}
