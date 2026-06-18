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
            class="px-1 text-center align-top w-8"
            :class="col.isWeekend ? 'bg-base-300' : 'bg-base-100'"
          >
            <div class="font-semibold leading-tight">{{ col.day }}</div>
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
              class="border-l border-base-200 h-8 cursor-default"
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
import { vacationForCell } from '../lib/vacationLookup.js';

const props = defineProps({
  groups: { type: Array, required: true },
  persons: { type: Array, required: true },
  vacations: { type: Array, required: true },
  fromMonth: { type: String, required: true },
  toMonth: { type: String, required: true },
  groupFilterId: { type: [String, null], default: 'all' },
});

const emit = defineEmits(['edit-vacation']);

const columns = computed(() => generateColumns(props.fromMonth, props.toMonth));

const filteredPersons = computed(() => {
  if (props.groupFilterId === 'all' || props.groupFilterId === undefined) return props.persons;
  return props.persons.filter((p) => p.groupId === props.groupFilterId);
});

const filteredGroups = computed(() => {
  if (props.groupFilterId === 'all' || props.groupFilterId === undefined) return props.groups;
  return props.groups.filter((g) => g.id === props.groupFilterId);
});

const sections = computed(() => groupPersons(filteredGroups.value, filteredPersons.value));

// Sammanhängande månads-spann för colspan.
const monthSpans = computed(() => {
  const spans = [];
  for (const col of columns.value) {
    const last = spans.at(-1);
    if (last && last.monthKey === col.monthKey) last.count++;
    else spans.push({ monthKey: col.monthKey, monthLabel: col.monthLabel, count: 1 });
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
  if (isVacation(personId, col.iso)) return 'bg-success cursor-pointer';
  return col.isWeekend ? 'bg-base-300' : '';
}

function onCellClick(personId, iso) {
  const vacation = vacationForCell(props.vacations, personId, iso);
  if (vacation) emit('edit-vacation', { vacation });
}
</script>
