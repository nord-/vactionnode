<template>
  <div class="flex flex-wrap items-end gap-3 mb-4">
    <label class="form-control">
      <span class="label-text">Från månad</span>
      <input
        type="month"
        class="input input-bordered input-sm"
        :value="fromMonth"
        @change="onRangeChange('from', $event.target.value)"
      />
    </label>
    <label class="form-control">
      <span class="label-text">Till månad</span>
      <input
        type="month"
        class="input input-bordered input-sm"
        :value="toMonth"
        @change="onRangeChange('to', $event.target.value)"
      />
    </label>
    <label class="form-control">
      <span class="label-text">Grupp</span>
      <select
        class="select select-bordered select-sm"
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
