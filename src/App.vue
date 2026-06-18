<template>
  <!-- Steg 1: inloggning -->
  <LoginView v-if="!authed" :error="loginError" @submit="onLogin" />

  <!-- Steg 2: välj person -->
  <PersonSelectView
    v-else-if="!myPersonId"
    :persons="store.persons.value"
    @select="onSelectPerson"
    @create="onCreatePerson"
  />

  <!-- Steg 3: huvudvy -->
  <div v-else class="p-4 max-w-full">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold">Semesterkalender</h1>
      <span class="text-sm opacity-70">Inloggad som {{ myName }}</span>
    </div>

    <div v-if="store.error.value" class="alert alert-error mb-4">
      <span>{{ store.error.value }}</span>
      <button class="btn btn-sm" @click="store.refresh()">Försök igen</button>
    </div>

    <AdminPanel
      :groups="store.groups.value"
      :persons="store.persons.value"
      @add-group="store.addGroup"
      @rename-group="(e) => store.renameGroup(e.id, e.name)"
      @remove-group="store.removeGroup"
      @add-person="(e) => store.addPerson(e.name, e.groupId)"
      @set-person-group="(e) => store.setPersonGroup(e.id, e.groupId)"
      @remove-person="onRemovePerson"
    />

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
import { ref, computed, onMounted } from 'vue';
import config from './config.js';
import { createGithubClient } from './lib/githubClient.js';
import { createDataStore } from './lib/dataStore.js';
import { createStore } from './composables/useStore.js';
import { defaultRange } from './composables/useDefaultRange.js';
import { isAuthenticated, login } from './lib/auth.js';
import { setPersonId, resolveIdentity } from './lib/identity.js';
import LoginView from './components/LoginView.vue';
import PersonSelectView from './components/PersonSelectView.vue';
import AdminPanel from './components/AdminPanel.vue';
import CalendarControls from './components/CalendarControls.vue';
import CalendarGrid from './components/CalendarGrid.vue';
import VacationModal from './components/VacationModal.vue';

const client = createGithubClient({
  owner: config.github.owner,
  repo: config.github.repo,
  branch: config.github.branch,
  token: config.github.token,
});
const dataStore = createDataStore({
  client,
  registryPath: config.github.registryPath,
  vacationsPath: config.github.vacationsPath,
});
const store = createStore(dataStore);

const authed = ref(isAuthenticated());
const loginError = ref(false);
const myPersonId = ref(null);

const range = ref(defaultRange(new Date()));
const groupFilterId = ref('all');

const modalOpen = ref(false);
const editing = ref(null);

const myName = computed(
  () => store.persons.value.find((p) => p.id === myPersonId.value)?.name ?? '',
);

onMounted(async () => {
  if (authed.value) await afterAuth();
});

async function onLogin(password) {
  if (login(password, config.password)) {
    authed.value = true;
    loginError.value = false;
    await afterAuth();
  } else {
    loginError.value = true;
  }
}

async function afterAuth() {
  await store.refresh();
  myPersonId.value = resolveIdentity(store.persons.value);
}

function onSelectPerson(id) {
  setPersonId(id);
  myPersonId.value = id;
}

async function onCreatePerson(name) {
  const created = await store.addPerson(name, null);
  setPersonId(created.id);
  myPersonId.value = created.id;
}

async function onRemovePerson(id) {
  await store.removePerson(id);
  if (id === myPersonId.value) myPersonId.value = resolveIdentity(store.persons.value);
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
  else await store.addVacation(myPersonId.value, start, end);
  modalOpen.value = false;
}

async function onDeleteVacation({ id }) {
  await store.removeVacation(id);
  modalOpen.value = false;
}
</script>
