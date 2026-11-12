<template>
  <dialog class="modal" :open="modelValue">
    <div class="modal-box">
      <h3 class="font-bold text-lg mb-2">
        {{ vacation ? 'Redigera semester' : 'Lägg till semester' }} — {{ personName }}
      </h3>
      <div class="flex flex-col gap-2">
        <div class="flex flex-col gap-1">
          <span class="text-sm">Startdatum</span>
          <DateField :key="`start-${fillCount}`" data-testid="start" v-model="start" />
        </div>
        <div class="flex flex-col gap-1">
          <span class="text-sm">Slutdatum</span>
          <DateField :key="`end-${fillCount}`" data-testid="end" v-model="end" :min="start" />
        </div>
        <p v-if="endBeforeStart" class="text-error text-sm">
          Slutdatum måste vara samma som eller efter startdatum.
        </p>
        <p v-if="error" data-testid="vacation-error" class="text-error text-sm">{{ error }}</p>
      </div>
      <div class="modal-action">
        <button
          v-if="vacation"
          data-testid="delete"
          class="btn btn-error mr-auto"
          @click="$emit('delete', { id: vacation.id })"
        >
          Ta bort
        </button>
        <button class="btn" @click="$emit('update:modelValue', false)">Avbryt</button>
        <button
          data-testid="save"
          class="btn btn-primary"
          :disabled="!isValid"
          @click="$emit('save', { start, end })"
        >
          Spara
        </button>
      </div>
    </div>
  </dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { isValidVacationRange } from '../lib/validation.js';
import DateField from './DateField.vue';

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  vacation: { type: Object, default: null },
  personName: { type: String, default: '' },
  error: { type: String, default: null },
  // Förvald dag för en ny semester, t.ex. den dag som klickades i kalendern.
  startDate: { type: String, default: '' },
});

defineEmits(['save', 'delete', 'update:modelValue']);

const start = ref('');
const end = ref('');
// Byter nyckel på datumfälten vid varje ifyllning, så att de monteras om och
// glömmer skriven text och felmeddelanden från förra öppningen.
const fillCount = ref(0);

// Fälten fylls i varje gång modalen öppnas och när en annan semester väljs:
// med semesterns datum vid redigering, annars med startDate (en dag) eller
// tomt. Annars låg förra öppningens inmatning kvar.
function fill() {
  start.value = props.vacation?.start ?? props.startDate;
  end.value = props.vacation?.end ?? props.startDate;
  fillCount.value++;
}
fill();

watch(
  [() => props.modelValue, () => props.vacation, () => props.startDate],
  ([isOpen]) => {
    if (isOpen) fill();
  },
);

// Slutdatumet följer med ett nytt startdatum när det är tomt eller hamnar
// före: en endagssemester kan sparas direkt, och ett slut som redan ligger
// efter starten (t.ex. vid redigering) lämnas orört.
watch(start, (newStart) => {
  if (newStart && (!end.value || end.value < newStart)) end.value = newStart;
});

const isValid = computed(() => isValidVacationRange(start.value, end.value));
// Meddelandet gäller bara ett faktiskt omvänt intervall. Tomma fält spärrar
// Spara via isValid men är inget fel att påpeka i en nyöppnad modal, och ett
// ogiltigt datum förklaras redan av datumfältet självt.
const endBeforeStart = computed(() => Boolean(start.value && end.value && end.value < start.value));
</script>
