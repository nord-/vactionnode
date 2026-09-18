<template>
  <form class="flex flex-col gap-2 max-w-sm" @submit.prevent="onSubmit">
    <input data-testid="current" type="password" class="input input-bordered"
           placeholder="Nuvarande lösenord" v-model="current" />
    <input data-testid="new" type="password" class="input input-bordered"
           placeholder="Nytt lösenord" v-model="next" />
    <input data-testid="confirm" type="password" class="input input-bordered"
           placeholder="Bekräfta nytt lösenord" v-model="confirm" />
    <p v-if="mismatch" class="text-error text-sm">Lösenorden stämmer inte överens.</p>
    <button class="btn btn-primary btn-sm" type="submit">Byt lösenord</button>
  </form>
</template>

<script setup>
import { ref } from 'vue';

const emit = defineEmits(['submit']);
const current = ref('');
const next = ref('');
const confirm = ref('');
const mismatch = ref(false);

function onSubmit() {
  mismatch.value = next.value !== confirm.value;
  if (mismatch.value) return;
  emit('submit', { currentPassword: current.value, newPassword: next.value });
}
</script>
