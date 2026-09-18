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
      @add-group="(name) => guard(store.addGroup(name))"
      @rename-group="(e) => guard(store.renameGroup(e.id, e.name))"
      @remove-group="(id) => guard(store.removeGroup(id))"
      @add-person="(payload) => guard(store.addPerson(payload))"
      @set-person-group="(e) => guard(store.setPersonGroup(e.id, e.groupId))"
      @remove-person="onRemovePerson"
      @set-password="onSetPassword"
    />

    <ChangePasswordView
      :error="changePasswordError"
      :success="changePasswordSuccess"
      @submit="onChangePassword"
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
      :error="vacationError"
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

// Delas mellan onUnauthorized och onLogout: båda måste rensa exakt samma
// tillstånd. Med bara den ena rensad beskrev isAdmin/myName fortfarande
// föregående användare efter en utloggning + ny inloggning, tills reload()
// hunnit svara — adminpanelen kunde då blinka fram för en icke-admin.
function clearSessionState() {
  user.value = null;
  store.groups.value = [];
  store.persons.value = [];
  store.vacations.value = [];
  store.me.value = null;
  store.error.value = null;
}

// onUnauthorized fångar en session som gått ut mitt i arbetet: appen faller
// tillbaka till inloggningsvyn i stället för att visa ett tomt rutnät. Även
// den gamla datan rensas ur store, annars hinner föregående vy blinka till
// innan reload() vid nästa inloggning hunnit svara.
const api = createApiClient({
  baseUrl: config.apiBaseUrl,
  onUnauthorized: clearSessionState,
});
const store = createStore(createDataStore({ api }));

// Fångar fel från mutate() så att en 422/403 inte blir en ohanterad
// promise-rejection. Felmeddelandet finns redan i store.error.value (satt av
// mutate) och visas av felbannern längst upp — den här funktionen behöver
// bara se till att avvisningen inte studsar vidare oregistrerad.
async function guard(promise) {
  try {
    await promise;
  } catch {
    // Redan synligt via store.error.value.
  }
}

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
  try {
    await logout(api);
  } catch {
    // Servern kunde inte nås eller svarade med fel — det lokala tillståndet
    // rensas ändå nedan, så användaren aldrig ser ut att fortfarande vara
    // inloggad. Utan try/catch här lämnade ett trasigt anrop en ohanterad
    // promise-rejection och användaren såg ut att vara kvar.
  }
  clearSessionState();
}

const changePasswordError = ref(null);
const changePasswordSuccess = ref(false);

async function onChangePassword({ currentPassword, newPassword }) {
  changePasswordError.value = null;
  changePasswordSuccess.value = false;
  try {
    await changePassword(api, currentPassword, newPassword);
    changePasswordSuccess.value = true;
  } catch (e) {
    changePasswordError.value = e.message;
  }
}

async function onRemovePerson(id) {
  await guard(store.removePerson(id));
}

async function onSetPassword({ id, password }) {
  await guard(store.updatePerson(id, { password }));
}

const vacationError = ref(null);

function openNewVacation() {
  editing.value = null;
  vacationError.value = null;
  modalOpen.value = true;
}
function openEditVacation({ vacation }) {
  editing.value = vacation;
  vacationError.value = null;
  modalOpen.value = true;
}

// Fångar fel explicit i stället för att låta store.mutate()s rethrow studsa
// vidare oregistrerad: modalen ligger som ett <dialog>-lager ovanpå resten av
// sidan, så felbannern längst upp (som store.error.value styr) syns aldrig
// medan modalen är öppen. Felet visas därför i modalen själv, och modalen
// hålls kvar öppen så användaren kan rätta och försöka igen.
async function onSaveVacation({ start, end }) {
  vacationError.value = null;
  try {
    if (editing.value) await store.updateVacation(editing.value.id, start, end);
    else await store.addVacation(store.me.value.id, start, end);
    modalOpen.value = false;
  } catch (e) {
    vacationError.value = e.message || 'Kunde inte spara semestern.';
  }
}

async function onDeleteVacation({ id }) {
  vacationError.value = null;
  try {
    await store.removeVacation(id);
    modalOpen.value = false;
  } catch (e) {
    vacationError.value = e.message || 'Kunde inte ta bort semestern.';
  }
}
</script>
