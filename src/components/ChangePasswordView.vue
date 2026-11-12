<template>
  <form class="flex flex-col gap-2 max-w-sm" @submit.prevent="onSubmit">
    <input data-testid="current" type="password" class="input input-bordered"
           placeholder="Nuvarande lösenord" v-model="current" />
    <input data-testid="new" type="password" class="input input-bordered"
           placeholder="Nytt lösenord" v-model="next" />
    <input data-testid="confirm" type="password" class="input input-bordered"
           placeholder="Bekräfta nytt lösenord" v-model="confirm" />
    <p v-if="mismatch" class="text-error text-sm">Lösenorden stämmer inte överens.</p>
    <p v-if="error" data-testid="change-password-error" class="text-error text-sm">{{ error }}</p>
    <p v-if="success" data-testid="change-password-success" class="text-success text-sm">
      Lösenordet har bytts.
    </p>
    <button class="btn btn-primary btn-sm" type="submit">Byt lösenord</button>
  </form>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
  error: { type: String, default: null },
  success: { type: Boolean, default: false },
});
const emit = defineEmits(['submit']);
const current = ref('');
const next = ref('');
const confirm = ref('');
const mismatch = ref(false);

// Fälten töms först när föräldern bekräftar ett lyckat byte (via success-
// propen), inte direkt vid submit — annars förlorar användaren sin
// inmatning om servern svarar med fel, t.ex. fel nuvarande lösenord.
watch(
  () => props.success,
  (isSuccess) => {
    if (!isSuccess) return;
    current.value = '';
    next.value = '';
    confirm.value = '';
  },
);

function onSubmit() {
  mismatch.value = false;
  if (next.value !== confirm.value) {
    mismatch.value = true;
    return;
  }
  emit('submit', { currentPassword: current.value, newPassword: next.value });
}
</script>
