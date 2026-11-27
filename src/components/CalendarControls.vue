<template>
  <div class="flex flex-wrap items-end gap-3 mb-4">
    <div class="flex flex-col gap-1">
      <span class="text-sm">Från månad</span>
      <MonthField label="Från månad" :model-value="fromMonth" @update:model-value="onRangeChange('from', $event)" />
    </div>
    <div class="flex flex-col gap-1">
      <span class="text-sm">Till månad</span>
      <MonthField label="Till månad" :model-value="toMonth" @update:model-value="onRangeChange('to', $event)" />
    </div>
    <label class="flex flex-col gap-1">
      <span class="text-sm">Grupp</span>
      <select
        data-testid="group-filter"
        class="select select-sm w-auto"
        :value="groupFilterId"
        @change="$emit('update:groupFilter', $event.target.value)"
      >
        <option value="all">Alla grupper</option>
        <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
      </select>
    </label>
    <div class="join">
      <button
        data-testid="prev-month"
        class="btn btn-sm join-item"
        aria-label="Föregående månad"
        title="Föregående månad"
        @click="stepMonth(-1)"
      >
        <span class="mdi mdi-chevron-left"></span>
      </button>
      <button
        data-testid="today"
        class="btn btn-sm join-item"
        title="Visa innevarande månad"
        @click="goToToday"
      >
        Idag
      </button>
      <button
        data-testid="next-month"
        class="btn btn-sm join-item"
        aria-label="Nästa månad"
        title="Nästa månad"
        @click="stepMonth(1)"
      >
        <span class="mdi mdi-chevron-right"></span>
      </button>
    </div>
    <button data-testid="add-vacation" class="btn btn-primary btn-sm ml-auto" @click="$emit('add-vacation')">
      <span class="mdi mdi-plus"></span> Lägg till semester
    </button>
  </div>
</template>

<script setup>
import { defaultRange } from '../composables/useDefaultRange.js';
import { shiftMonthRange } from '../lib/dates.js';
import { clampMonthRange } from '../lib/validation.js';
import MonthField from './MonthField.vue';

const props = defineProps({
  fromMonth: { type: String, required: true },
  toMonth: { type: String, required: true },
  groups: { type: Array, required: true },
  groupFilterId: { type: [String, null], default: 'all' },
});

const emit = defineEmits(['update:range', 'update:groupFilter', 'add-vacation']);

function onRangeChange(changed, value) {
  const from = changed === 'from' ? value : props.fromMonth;
  const to = changed === 'to' ? value : props.toMonth;
  emit('update:range', clampMonthRange(from, to, changed));
}

function goToToday() {
  emit('update:range', defaultRange(new Date()));
}

function stepMonth(delta) {
  emit('update:range', shiftMonthRange({ from: props.fromMonth, to: props.toMonth }, delta));
}
</script>
