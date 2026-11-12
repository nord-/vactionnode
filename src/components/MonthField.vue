<template>
  <div class="flex gap-1">
    <template v-for="part in order" :key="part">
      <select
        v-if="part === 'month'"
        class="select select-sm w-auto"
        :aria-label="label ? `${label}, månad` : undefined"
        :value="month"
        @change="emitMonth($event.target.value, year)"
      >
        <option v-for="(name, i) in names" :key="i" :value="pad(i + 1)">{{ name }}</option>
      </select>
      <input
        v-else
        type="number"
        min="1000"
        max="9999"
        class="input input-sm w-24"
        :aria-label="label ? `${label}, år` : undefined"
        :value="year"
        @input="emitMonth(month, $event.target.value)"
        @change="resetIncompleteYear($event.target)"
      />
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { monthNames, monthYearOrder } from '../lib/dateFormat.js';
import { userLocale } from '../lib/locale.js';

// Månadsväljare i användarens språk ("juni 2026") i stället för
// <input type="month">, som följer webbläsarens visningsspråk.
const props = defineProps({
  modelValue: { type: String, required: true },
  locale: { type: String, default: () => userLocale() },
  // Fältets etikett; ger månadslistan och årsrutan var sitt tillgängligt namn.
  label: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue']);

const names = computed(() => monthNames(props.locale));
const order = computed(() => monthYearOrder(props.locale));
const year = computed(() => props.modelValue.slice(0, 4));
const month = computed(() => props.modelValue.slice(5, 7));

function pad(n) {
  return String(n).padStart(2, '0');
}

function isFullYear(y) {
  return /^\d{4}$/.test(String(y));
}

// Ett år som ännu inte är fyrsiffrigt (mitt i inmatningen) skickas inte vidare.
function emitMonth(m, y) {
  if (!isFullYear(y)) return;
  emit('update:modelValue', `${y}-${m}`);
}

// Lämnas fältet med ett ofullständigt år visas året kalendern faktiskt
// använder igen, i stället för t.ex. "202" medan kalendern visar 2026.
function resetIncompleteYear(input) {
  if (!isFullYear(input.value)) input.value = year.value;
}
</script>
