<template>
  <!-- Steg 1: inloggning -->
  <LoginView v-if="!user" :error="loginError" @submit="onLogin" />

  <!-- Steg 2: huvudvy -->
  <div v-else class="p-4 max-w-full">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold">Semesterkalender</h1>
      <div class="flex items-center gap-4">
        <span class="text-sm opacity-70">Inloggad som {{ myName }}</span>
        <button class="btn btn-ghost btn-sm" @click="onLogout">Logga ut</button>
      </div>
    </div>

    <div v-if="store.error.value" class="alert alert-error mb-4">
      <span>{{ store.error.value }}</span>
      <button class="btn btn-sm" @click="reload">Försök igen</button>
    </div>

    <AdminPanel
      v-if="isAdmin"
      :groups="store.groups.value"
      :persons="store.persons.value"
      @add-group="store.addGroup"
      @rename-group="(e) => store.renameGroup(e.id, e.name)"
      @remove-group="store.removeGroup"
      @add-person="store.addPerson"
      @set-person-group="(e) => store.setPersonGroup(e.id, e.groupId)"
      @remove-person="onRemovePerson"
    />

    <ChangePasswordView @submit="onChangePassword" />

    <CalendarControls
      :from-month="range.from"
      :to-month="range.to"
      :groups="store.groups.value"
      :group-filter-id="groupFilterId"
      @update:range="range = $event"
      @update:groupFilter="groupFilterId = $event"
      @add-vacation="openNewVacation"
    />

    <CalendarGrid
      :groups="store.groups.value"
      :persons="store.persons.value"
      :vacations="store.vacations.value"
      :from-month="range.from"
      :to-month="range.to"
      :group-filter-id="groupFilterId"
      @edit-vacation="openEditVacation"
    />

    <VacationModal
      v-model="modalOpen"
      :vacation="editing"
      :person-name="myName"
      @save="onSaveVacation"
      @delete="onDeleteVacation"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import config from './config.js';
import { createApiClient } from './lib/apiClient.js';
import { createDataStore } from './lib/dataStore.js';
import { createStore } from './composables/useStore.js';
import { defaultRange, monthRangeToDates } from './composables/useDefaultRange.js';
import { login, logout, currentUser, changePassword } from './lib/auth.js';
import LoginView from './components/LoginView.vue';
import ChangePasswordView from './components/ChangePasswordView.vue';
import AdminPanel from './components/AdminPanel.vue';
import CalendarControls from './components/CalendarControls.vue';
import CalendarGrid from './components/CalendarGrid.vue';
import VacationModal from './components/VacationModal.vue';

// onUnauthorized fångar en session som gått ut mitt i arbetet: appen faller
// tillbaka till inloggningsvyn i stället för att visa ett tomt rutnät.
const api = createApiClient({
  baseUrl: config.apiBaseUrl,
  onUnauthorized: () => { user.value = null; },
});
const store = createStore(createDataStore({ api }));

const user = ref(null);
const loginError = ref(null);          // sträng, inte boolean
const isAdmin = computed(() => store.me.value?.isAdmin === true);
const myName = computed(() => store.me.value?.name ?? '');

const range = ref(defaultRange(new Date()));
const groupFilterId = ref('all');

const modalOpen = ref(false);
const editing = ref(null);

function reload() {
  const { from, to } = monthRangeToDates(range.value);
  return store.refresh(from, to);
}

onMounted(async () => {
  try {
    user.value = await currentUser(api);
  } catch {
    user.value = null;
  }
  if (user.value) await reload();
});

watch(range, reload);

async function onLogin({ email, password }) {
  loginError.value = null;
  try {
    user.value = await login(api, email, password);
    await reload();
  } catch (e) {
    loginError.value = e.message;
  }
}

async function onLogout() {
  await logout(api);
  user.value = null;
}

function onChangePassword({ currentPassword, newPassword }) {
  return changePassword(api, currentPassword, newPassword);
}

async function onRemovePerson(id) {
  await store.removePerson(id);
}

function openNewVacation() {
  editing.value = null;
  modalOpen.value = true;
}
function openEditVacation({ vacation }) {
  editing.value = vacation;
  modalOpen.value = true;
}

async function onSaveVacation({ start, end }) {
  if (editing.value) await store.updateVacation(editing.value.id, start, end);
  else await store.addVacation(store.me.value.id, start, end);
  modalOpen.value = false;
}

async function onDeleteVacation({ id }) {
  await store.removeVacation(id);
  modalOpen.value = false;
}
</script>
