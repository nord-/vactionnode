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
