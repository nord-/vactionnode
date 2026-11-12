<template>
  <div v-bind="$attrs" class="flex flex-col gap-1">
    <VueDatePicker
      v-model="model"
      :locale="pickerLocale"
      :formats="{ input: formatInput }"
      :text-input="{ format: parseInput, applyOnBlur: true }"
      :week-start="1"
      :week-numbers="{ type: 'iso', label: 'v' }"
      :time-config="{ enableTimePicker: false }"
      :placeholder="placeholder"
      auto-apply
      teleport
      @text-input="onTextInput"
      @blur="onBlur"
      @closed="onBlur"
    />
    <p v-if="invalid" class="text-error text-sm">Ogiltigt datum.</p>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { VueDatePicker } from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css';
import { formatDate, parseDate } from '../lib/dateFormat.js';
import { dateFnsLocale } from '../lib/dateFnsLocale.js';
import { userLocale } from '../lib/locale.js';

// data-testid m.fl. hamnar på omslaget; textfältet nås via omslaget.
defineOptions({ inheritAttrs: false });

const props = defineProps({
  modelValue: { type: String, default: '' },
  locale: { type: String, default: () => userLocale() },
});
const emit = defineEmits(['update:modelValue']);

// Popupens månadsnamn och veckodagar följer användarens språk via date-fns;
// textfältet formateras och tolkas med Intl, samma som resten av appen.
const pickerLocale = computed(() => dateFnsLocale(props.locale));

function toIso(date) {
  const pad = (n) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

// Lokal midnatt, så att datumet inte glider en dag i någon tidszon.
function fromIso(iso) {
  const [y, m, d] = iso.split('-').map(Number);
  return new Date(y, m - 1, d);
}

// Biblioteket får ett Date (eller null), appen ser ISO-strängar (eller '').
// model-type="yyyy-MM-dd" går inte att använda: i v14 ignoreras model-type
// när formats.input är en funktion, och v-model fick då den formaterade
// texten ("16.6.2026") i stället för ISO.
const model = computed({
  get: () => (props.modelValue ? fromIso(props.modelValue) : null),
  set: (value) => emit('update:modelValue', value ? toIso(value) : ''),
});

function formatInput(date) {
  return formatDate(toIso(date), props.locale);
}

// Anropas med texten användaren skrivit, men i v14 också med det valda
// datumet (som Date) för att fylla textfältet när det får fokus.
function parseInput(value) {
  if (value instanceof Date) return formatInput(value);
  const iso = parseDate(value, props.locale);
  return iso ? fromIso(iso) : null;
}

const today = new Date();
const placeholder = computed(() => `t.ex. ${formatInput(today)}`);

// Felmeddelandet visas först när fältet lämnas, inte mitt i inmatningen.
const lastText = ref('');
const touched = ref(false);
const invalid = computed(() => touched.value
  && lastText.value.trim() !== ''
  && parseDate(lastText.value, props.locale) === null);

function onTextInput(event) {
  lastText.value = typeof event === 'string' ? event : event.target.value;
  touched.value = false;
}

function onBlur() {
  touched.value = true;
}
</script>
