<template>
  <dialog class="modal" :open="modelValue">
    <div class="modal-box">
      <h3 class="font-bold text-lg mb-2">
        {{ vacation ? 'Redigera semester' : 'Lägg till semester' }} — {{ personName }}
      </h3>
      <div class="flex flex-col gap-2">
        <label class="form-control">
          <span class="label-text">Startdatum</span>
          <input data-testid="start" type="date" class="input input-bordered" v-model="start" />
        </label>
        <label class="form-control">
          <span class="label-text">Slutdatum</span>
          <input data-testid="end" type="date" class="input input-bordered" v-model="end" />
        </label>
        <p v-if="!isValid" class="text-error text-sm">
          Slutdatum måste vara samma som eller efter startdatum.
        </p>
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

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  vacation: { type: Object, default: null },
  personName: { type: String, default: '' },
});

defineEmits(['save', 'delete', 'update:modelValue']);

const start = ref(props.vacation?.start ?? '');
const end = ref(props.vacation?.end ?? '');

// Synka fälten när en annan semester öppnas.
watch(
  () => props.vacation,
  (v) => {
    start.value = v?.start ?? '';
    end.value = v?.end ?? '';
  },
);

const isValid = computed(() => isValidVacationRange(start.value, end.value));
</script>
