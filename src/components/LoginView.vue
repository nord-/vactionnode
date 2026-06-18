<template>
  <div class="min-h-screen flex items-center justify-center bg-base-200">
    <form class="card bg-base-100 shadow-md p-6 w-80" @submit.prevent="$emit('submit', password)">
      <h1 class="text-xl font-bold mb-4">Logga in</h1>
      <input
        type="password"
        class="input input-bordered w-full mb-2"
        placeholder="Lösenord"
        v-model="password"
      />
      <p v-if="error" class="text-error text-sm mb-2">Fel lösenord. Försök igen.</p>
      <button type="submit" class="btn btn-primary w-full">Logga in</button>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue';
defineProps({ error: { type: Boolean, default: false } });
defineEmits(['submit']);
const password = ref('');
</script>
