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
      :min-date="minDate"
      :start-date="minDate"
      :input-attrs="{ id: inputId || undefined }"
      auto-apply
      teleport
      @text-input="onTextInput"
      @blur="onBlur"
      @closed="onBlur"
    />
    <p v-if="error" class="text-error text-sm">{{ error }}</p>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
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
  // Tidigaste valbara dag (ISO); kalendern öppnas också på den månaden.
  min: { type: String, default: '' },
  // id på själva textfältet, så att en <label for> kan peka på det.
  inputId: { type: String, default: '' },
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
  set: (value) => {
    // Texten glöms först när ett giltigt datum valts: när biblioteket själv
    // tömmer fältet ska felet för den avvisade texten ligga kvar.
    if (value) lastText.value = '';
    emit('update:modelValue', value ? toIso(value) : '');
  },
});

const minDate = computed(() => (props.min ? fromIso(props.min) : undefined));

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

// Texten användaren senast skrev, tills biblioteket godtar ett värde.
const lastText = ref('');
const touched = ref(false);

// Varför den skrivna texten inte kan användas, eller null om den kan det.
// immediate: ett datum före min tömmer biblioteket själv ur fältet, så det
// måste förklaras direkt; annars försvinner datumet i tysthet.
function textProblem(text) {
  if (text.trim() === '') return null;
  const iso = parseDate(text, props.locale);
  if (!iso) return { message: 'Ogiltigt datum.', immediate: false };
  if (props.min && iso < props.min) {
    return {
      message: `Datumet kan inte vara före ${formatDate(props.min, props.locale)}.`,
      immediate: true,
    };
  }
  return null;
}

// Ett giltigt värde utifrån (annan semester, slutdatum som följer start)
// ersätter den text som felet gällde.
watch(
  () => props.modelValue,
  (value) => {
    if (value) lastText.value = '';
  },
);

// Ett ogiltigt datum visas först när fältet lämnas, inte mitt i inmatningen.
const error = computed(() => {
  const problem = textProblem(lastText.value);
  return problem && (problem.immediate || touched.value) ? problem.message : null;
});

function onTextInput(event) {
  lastText.value = typeof event === 'string' ? event : event.target.value;
  touched.value = false;
}

// Biblioteket behåller sitt gamla värde när texten avvisas, medan fältet
// visar den nya texten. Värdet töms därför när fältet lämnas, så att
// föräldern inte sparar något annat än det som står i fältet. Det görs inte
// medan man skriver: ett tomt värde utifrån får biblioteket att tömma
// textfältet, och då försvann det användaren höll på att skriva. Spara
// lämnar fältet innan klicket når knappen, så inget gammalt värde sparas.
function onBlur() {
  touched.value = true;
  if (textProblem(lastText.value)) emit('update:modelValue', '');
}
</script>
