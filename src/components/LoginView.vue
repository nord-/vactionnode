<template>
  <div class="min-h-screen flex items-center justify-center bg-base-200">
    <form class="card bg-base-100 shadow-md p-6 w-80" @submit.prevent="onSubmit">
      <h1 class="text-xl font-bold mb-4">Logga in</h1>
      <input
        data-testid="email"
        type="email"
        class="input w-full mb-2"
        placeholder="E-post"
        v-model="email"
      />
      <input
        data-testid="password"
        type="password"
        class="input w-full mb-2"
        placeholder="Lösenord"
        v-model="password"
      />
      <p v-if="error" class="text-error text-sm mb-2">{{ error }}</p>
      <button type="submit" class="btn btn-primary w-full">Logga in</button>
      <!-- En vanlig länk, inte fetch: Microsoft-inloggningen är en
           helsidesnavigering som slutar med en redirect tillbaka hit. -->
      <template v-if="microsoft">
        <div class="divider text-sm opacity-70">eller</div>
        <a data-testid="microsoft" :href="ssoUrl" class="btn btn-outline w-full">
          <span class="mdi mdi-microsoft"></span> Logga in med Microsoft
        </a>
      </template>
    </form>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import config from '../config.js';

// error är nu en sträng från servern, inte en boolean. Meddelandet skiljer på
// fel lösenord, spärrat konto och nätverksfel — den gamla fasta texten
// "Fel lösenord" hade varit direkt missvisande vid 429.
defineProps({
  error: { type: String, default: null },
  // Servern säger om Microsoft-inloggningen är konfigurerad.
  microsoft: { type: Boolean, default: false },
});
const ssoUrl = `${config.apiBaseUrl}/session/sso`;
const emit = defineEmits(['submit']);

const email = ref('');
const password = ref('');

function onSubmit() {
  emit('submit', { email: email.value, password: password.value });
}
</script>
