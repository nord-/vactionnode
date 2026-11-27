<template>
  <div class="overflow-x-auto border border-base-300 rounded">
    <table class="border-collapse text-sm">
      <thead>
        <!-- Rad 1: månad -->
        <tr>
          <th class="sticky left-0 z-20 bg-base-200 min-w-[12rem] text-left px-2"></th>
          <th
            v-for="m in monthSpans"
            :key="m.monthKey"
            :colspan="m.count"
            class="bg-base-200 px-2 py-1 text-left font-bold whitespace-nowrap border-l border-base-300"
          >
            {{ m.monthLabel }}
          </th>
        </tr>
        <!-- Rad 2: veckonummer -->
        <tr>
          <th class="sticky left-0 z-20 bg-base-200 px-2"></th>
          <th
            v-for="w in weekSpans"
            :key="w.key"
            :colspan="w.count"
            class="bg-base-100 px-1 text-center text-xs font-semibold border-l border-base-300 whitespace-nowrap"
          >
            v.{{ w.isoWeek }}
          </th>
        </tr>
        <!-- Rad 3: datum + veckodag -->
        <tr>
          <th class="sticky left-0 z-20 bg-base-200 px-2 text-left">Person</th>
          <th
            v-for="col in columns"
            :key="col.iso"
            data-testid="day-header"
            :data-date="col.iso"
            :title="col.holidayName"
            class="px-1 text-center align-top w-8"
            :class="[col.isWeekend || col.holidayName ? 'bg-base-300' : 'bg-base-100', { 'opacity-60': !col.inSelectedMonths }]"
          >
            <div class="font-semibold leading-tight" :class="{ 'text-error': col.holidayName }">{{ col.day }}</div>
            <div class="text-[10px] opacity-70 leading-tight">{{ col.weekdayLabel }}</div>
          </th>
        </tr>
      </thead>
      <tbody>
        <template v-for="section in sections" :key="section.group.id ?? 'ungrouped'">
          <tr>
            <th
              :colspan="columns.length + 1"
              class="sticky left-0 bg-primary text-primary-content text-left px-2 py-1 font-bold"
            >
              {{ section.group.name }}
            </th>
          </tr>
          <tr v-for="person in section.persons" :key="person.id" class="hover:bg-base-200">
            <td class="sticky left-0 z-10 bg-base-100 px-2 pl-6 whitespace-nowrap">
              {{ person.name }}
            </td>
            <td
              v-for="col in columns"
              :key="col.iso"
              class="border-l border-base-200 h-8"
              :class="cellClass(person.id, col)"
              :data-vacation="isVacation(person.id, col.iso) ? 'true' : 'false'"
              @click="onCellClick(person.id, col.iso)"
            ></td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { generateColumns } from '../lib/dates.js';
import { groupPersons } from '../lib/grouping.js';
import { holidayMap } from '../lib/holidays.js';
import { vacationForCell } from '../lib/vacationLookup.js';

const props = defineProps({
  groups: { type: Array, required: true },
  persons: { type: Array, required: true },
  vacations: { type: Array, required: true },
  holidays: { type: Array, default: () => [] },
  fromMonth: { type: String, required: true },
  toMonth: { type: String, required: true },
  groupFilterId: { type: [String, null], default: 'all' },
  // Om personens rad får skrivas: en tom dag lägger då till semester och en
  // semestercell öppnar den. Rättighetsregeln ägs av föräldern; rutnätet
  // bara frågar, och visar pekarmarkör bara där ett klick gör något.
  canAddFor: { type: Function, default: () => false },
});

const emit = defineEmits(['edit-vacation', 'add-vacation']);

// Helgdagsnamnet läggs på kolumnerna här i stället för i dates.js, som
// förblir ren kalenderlogik utan kännedom om helgdagar.
const columns = computed(() => {
  const names = holidayMap(props.holidays);
  return generateColumns(props.fromMonth, props.toMonth).map((col) => ({
    ...col,
    holidayName: names.get(col.iso) ?? null,
  }));
});

const filteredPersons = computed(() => {
  if (props.groupFilterId === 'all' || props.groupFilterId === undefined) return props.persons;
  return props.persons.filter((p) => p.groupId === props.groupFilterId);
});

const filteredGroups = computed(() => {
  if (props.groupFilterId === 'all' || props.groupFilterId === undefined) return props.groups;
  return props.groups.filter((g) => g.id === props.groupFilterId);
});

const sections = computed(() => groupPersons(filteredGroups.value, filteredPersons.value));

// Sammanhängande månads-spann för colspan. Utfyllnadsdagarna utanför de
// valda månaderna får ett eget spann utan rubrik: "FEBRUARI 2027" över en
// enda kolumn skulle annars tvinga kolumnen att bli bred.
const monthSpans = computed(() => {
  const spans = [];
  for (const col of columns.value) {
    const monthKey = col.inSelectedMonths ? col.monthKey : `pad-${col.monthKey}`;
    const last = spans.at(-1);
    if (last && last.monthKey === monthKey) last.count++;
    else {
      spans.push({
        monthKey,
        monthLabel: col.inSelectedMonths ? col.monthLabel : '',
        count: 1,
      });
    }
  }
  return spans;
});

// Sammanhängande vecko-spann för colspan. Jämför på ISO-veckans ISO-år så att
// en vecka som spänner över ett årsskifte inte felaktigt splittras i headern.
const weekSpans = computed(() => {
  const spans = [];
  for (const col of columns.value) {
    const last = spans.at(-1);
    if (last && last.isoWeek === col.isoWeek && last.isoWeekYear === col.isoWeekYear) {
      last.count++;
    } else {
      spans.push({
        key: `${col.isoWeekYear}-${col.isoWeek}`,
        isoWeek: col.isoWeek,
        isoWeekYear: col.isoWeekYear,
        count: 1,
      });
    }
  }
  return spans;
});

function isVacation(personId, iso) {
  return vacationForCell(props.vacations, personId, iso) !== null;
}

function cellClass(personId, col) {
  if (isVacation(personId, col.iso)) {
    // En semesterdag på en helg- eller röd dag tonas ned, så att bakgrunden syns igenom.
    const color = col.isWeekend || col.holidayName ? 'bg-success/40' : 'bg-success';
    return props.canAddFor(personId) ? `${color} cursor-pointer` : color;
  }
  const classes = [col.isWeekend || col.holidayName ? 'bg-base-300' : ''];
  if (props.canAddFor(personId)) classes.push('cursor-pointer hover:bg-primary/20');
  return classes;
}

function onCellClick(personId, iso) {
  const vacation = vacationForCell(props.vacations, personId, iso);
  if (vacation) emit('edit-vacation', { vacation });
  else if (props.canAddFor(personId)) emit('add-vacation', { personId, date: iso });
}
</script>
