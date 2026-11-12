<template>
  <dialog
    ref="dialog"
    data-testid="change-password-dialog"
    class="modal"
    :open="modelValue"
    aria-labelledby="change-password-title"
    @keydown="onKeydown"
  >
    <form class="modal-box" @submit.prevent="onSubmit">
      <h3 id="change-password-title" class="font-bold text-lg mb-2">Byt lösenord</h3>
      <div class="flex flex-col gap-2">
        <input data-testid="current" type="password" class="input w-full"
               placeholder="Nuvarande lösenord" aria-label="Nuvarande lösenord"
               autocomplete="current-password" v-model="current" />
        <input data-testid="new" type="password" class="input w-full"
               placeholder="Nytt lösenord" aria-label="Nytt lösenord"
               autocomplete="new-password" v-model="next" />
        <input data-testid="confirm" type="password" class="input w-full"
               placeholder="Bekräfta nytt lösenord" aria-label="Bekräfta nytt lösenord"
               autocomplete="new-password" v-model="confirm" />
        <p v-if="mismatch" class="text-error text-sm">Lösenorden stämmer inte överens.</p>
        <p v-if="error" data-testid="change-password-error" class="text-error text-sm">{{ error }}</p>
        <p v-if="success" data-testid="change-password-success" class="text-success text-sm">
          Lösenordet har bytts.
        </p>
      </div>
      <div class="modal-action">
        <button data-testid="cancel-change-password" class="btn" type="button" @click="close">
          Avbryt
        </button>
        <button class="btn btn-primary" type="submit">Byt lösenord</button>
      </div>
    </form>
  </dialog>
</template>

<script setup>
import { ref, watch, onUnmounted } from 'vue';
import { useDialogFocus } from '../composables/useDialogFocus.js';

// Hur länge bekräftelsen syns innan modalen stänger sig själv.
const CLOSE_DELAY_MS = 1500;

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  error: { type: String, default: null },
  success: { type: Boolean, default: false },
});
const emit = defineEmits(['submit', 'update:modelValue']);
const current = ref('');
const next = ref('');
const confirm = ref('');
const mismatch = ref(false);
let closeTimer = null;

const dialog = ref(null);
const { onKeydown } = useDialogFocus(dialog, () => props.modelValue, close);

function clearFields() {
  current.value = '';
  next.value = '';
  confirm.value = '';
  mismatch.value = false;
}

function cancelCloseTimer() {
  clearTimeout(closeTimer);
  closeTimer = null;
}

function close() {
  emit('update:modelValue', false);
}

// Varje öppning börjar från tomma fält; en avbruten inmatning ska inte
// ligga kvar till nästa gång. En väntande automatisk stängning från ett
// tidigare byte får inte heller stänga den nyöppnade modalen.
watch(
  () => props.modelValue,
  (isOpen) => {
    cancelCloseTimer();
    if (isOpen) clearFields();
  },
);

// Fälten töms först när föräldern bekräftar ett lyckat byte (via success-
// propen), inte direkt vid submit — annars förlorar användaren sin
// inmatning om servern svarar med fel, t.ex. fel nuvarande lösenord.
watch(
  () => props.success,
  (isSuccess) => {
    if (!isSuccess) return;
    clearFields();
    closeTimer = setTimeout(close, CLOSE_DELAY_MS);
  },
);

onUnmounted(cancelCloseTimer);

function onSubmit() {
  mismatch.value = false;
  if (next.value !== confirm.value) {
    mismatch.value = true;
    return;
  }
  emit('submit', { currentPassword: current.value, newPassword: next.value });
}
</script>
