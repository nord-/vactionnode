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
