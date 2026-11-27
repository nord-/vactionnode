<template>
  <div class="card bg-base-100 shadow-sm">
    <div class="card-body">
      <h2 class="card-title">Helgdagar</h2>

      <div class="flex items-center gap-2 mb-2">
        <button data-testid="holiday-prev" aria-label="Föregående år" class="btn btn-ghost btn-sm" :disabled="year <= MIN_YEAR" @click="step(-1)">
          <span class="mdi mdi-chevron-left"></span>
        </button>
        <span data-testid="holiday-year" class="font-semibold">{{ year }}</span>
        <button data-testid="holiday-next" aria-label="Nästa år" class="btn btn-ghost btn-sm" :disabled="year >= MAX_YEAR" @click="step(1)">
          <span class="mdi mdi-chevron-right"></span>
        </button>
        <button data-testid="import-holidays" class="btn btn-sm ml-auto" :disabled="busy" @click="importYear">
          Importera svenska helgdagar för {{ year }}
        </button>
      </div>

      <div v-if="receipt" data-testid="holiday-receipt" class="alert alert-success text-sm flex-col items-start gap-0">
        <p>{{ receipt.counts }}</p>
        <p v-if="receipt.untranslated">{{ receipt.untranslated }}</p>
      </div>
      <div v-if="error" data-testid="holiday-error" class="alert alert-error text-sm">{{ error }}</div>

      <ul class="flex flex-col gap-1 mb-3">
        <li v-for="h in holidays" :key="h.id" class="flex items-center gap-2">
          <span class="w-36 text-sm">{{ formatDate(h.date, locale) }}</span>
          <input
            :data-testid="`holiday-name-${h.id}`"
            class="input input-sm flex-1"
            :value="h.name"
            @change="rename(h, $event.target)"
          />
          <button :data-testid="`remove-holiday-${h.id}`" :aria-label="`Ta bort ${h.name}`" class="btn btn-ghost btn-sm" @click="remove(h.id)">
            <span class="mdi mdi-delete"></span>
          </button>
        </li>
      </ul>

      <div class="flex flex-wrap gap-2">
        <DateField data-testid="new-holiday-date" v-model="newDate" />
        <input data-testid="new-holiday-name" class="input input-sm flex-1" placeholder="Namn" v-model="newName" />
        <button data-testid="add-holiday" class="btn btn-primary btn-sm" :disabled="busy || !newDate || !newName.trim()" @click="add">
          Lägg till
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import DateField from './DateField.vue';
import { formatDate } from '../lib/dateFormat.js';
import { userLocale } from '../lib/locale.js';

const props = defineProps({
  dataStore: { type: Object, required: true },
  initialYear: { type: Number, default: () => new Date().getFullYear() },
});
const emit = defineEmits(['changed']);

// Samma gränser som servern.
const MIN_YEAR = 2000;
const MAX_YEAR = 2100;

const locale = userLocale();
const year = ref(props.initialYear);
const holidays = ref([]);
const error = ref('');
const receipt = ref(null);
const newDate = ref('');
const newName = ref('');
const busy = ref(false);

function reset() {
  error.value = '';
  receipt.value = null;
}

function fail(e) {
  error.value = e?.message ?? 'Ett fel uppstod.';
}

// Hämtar årets lista. Ett sent svar för ett tidigare år, lyckat eller avvisat,
// får inte påverka det år som visas nu. Ett misslyckat omladdningsförsök visar
// bara sitt fel på felraden.
async function reload() {
  const shown = year.value;
  try {
    const rows = await props.dataStore.listHolidays(shown);
    if (shown === year.value) holidays.value = rows;
  } catch (e) {
    if (shown === year.value) fail(e);
  }
}

// Kör en skrivning: töm gamla fel och kvitton, ladda om listan och säg till
// att kalendern ska hämtas på nytt. Returnerar skrivningens svar, eller
// undefined om den avvisades (felet visas då på felraden). En lyckad skrivning
// räknas alltid som lyckad, även om omladdningen efteråt misslyckas. Medan en
// skrivning pågår är Importera och Lägg till avstängda, så att ett dubbelklick
// inte ger ett vilseledande kvitto eller ett falskt dubblettfel.
async function perform(action) {
  reset();
  busy.value = true;
  try {
    let result;
    try {
      result = await action();
    } catch (e) {
      fail(e);
      return undefined;
    }
    await reload();
    emit('changed');
    return result;
  } finally {
    busy.value = false;
  }
}

// Hämtar årets lista utan att skriva något, så inget changed emittas.
function show() {
  reset();
  return reload();
}

function step(delta) {
  year.value += delta;
  return show();
}

async function rename(holiday, field) {
  // Läses före perform: en omrendering under omladdningen skriver över fältet.
  const name = field.value;
  const ok = await perform(async () => {
    await props.dataStore.renameHoliday(holiday.id, name);
    return true;
  });
  // Avvisat namn: fältet visar det sparade namnet igen. Sparat namn: raden
  // får det direkt, så fältet består även om omladdningen misslyckades.
  if (ok) holiday.name = name;
  else field.value = holiday.name;
}

function remove(id) {
  return perform(() => props.dataStore.removeHoliday(id));
}

async function add() {
  const ok = await perform(async () => {
    await props.dataStore.addHoliday(newDate.value, newName.value.trim());
    return true;
  });
  if (ok) {
    newDate.value = '';
    newName.value = '';
  }
}

async function importYear() {
  const result = await perform(() => props.dataStore.importHolidays(year.value));
  if (!result) return;
  receipt.value = {
    counts: `${result.imported} importerade, ${result.skipped} fanns redan.`,
    untranslated: result.untranslated?.length
      ? `Okända namn sparades på engelska: ${result.untranslated.join(', ')}.`
      : '',
  };
}

onMounted(show);
</script>
