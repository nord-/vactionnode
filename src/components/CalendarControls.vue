<template>
  <div class="flex flex-wrap items-end gap-3 mb-4">
    <label class="flex flex-col gap-1">
      <span class="text-sm">Från månad</span>
      <input
        type="month"
        class="input input-sm w-auto"
        :value="fromMonth"
        @change="onRangeChange('from', $event.target.value)"
      />
    </label>
    <label class="flex flex-col gap-1">
      <span class="text-sm">Till månad</span>
      <input
        type="month"
        class="input input-sm w-auto"
        :value="toMonth"
        @change="onRangeChange('to', $event.target.value)"
      />
    </label>
    <label class="flex flex-col gap-1">
      <span class="text-sm">Grupp</span>
      <select
        class="select select-sm w-auto"
        :value="groupFilterId"
        @change="$emit('update:groupFilter', $event.target.value)"
      >
        <option value="all">Alla grupper</option>
        <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
      </select>
    </label>
    <button data-testid="add-vacation" class="btn btn-primary btn-sm ml-auto" @click="$emit('add-vacation')">
      <span class="mdi mdi-plus"></span> Lägg till semester
    </button>
  </div>
</template>

<script setup>
import { clampMonthRange } from '../lib/validation.js';

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
</script>
