<template>
  <!-- Steg 1: inloggning -->
  <LoginView v-if="!user" :error="loginError" :microsoft="microsoftLogin" @submit="onLogin" />

  <!-- Steg 2: huvudvy -->
  <div v-else class="p-4 max-w-full min-h-screen bg-base-200">
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold"><a href="#/">Semesterkalender</a></h1>
      <div class="flex items-center gap-2">
        <span class="text-sm opacity-70">Inloggad som {{ myName }}</span>
        <!-- daisyUI-dropdownen hålls öppen så länge något i den har fokus;
             closeMenu() tar bort fokuset så att den stängs efter ett val. -->
        <div class="dropdown dropdown-end">
          <div tabindex="0" role="button" class="btn btn-ghost btn-sm" aria-label="Meny">
            <span class="mdi mdi-menu text-xl"></span>
          </div>
          <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box shadow z-10 w-64 p-2">
            <li>
              <a data-testid="menu-calendar" href="#/"
                 :class="{ 'menu-active': page === 'calendar' }" @click="closeMenu">
                <span class="mdi mdi-calendar"></span> Kalender
              </a>
            </li>
            <li v-if="isAdmin">
              <a data-testid="menu-admin" href="#/admin"
                 :class="{ 'menu-active': page === 'admin' }" @click="closeMenu">
                <span class="mdi mdi-account-group"></span> Hantera personer & grupper
              </a>
            </li>
            <!-- Ett tomt <li> ritas som avdelare i daisyUI:s menu. -->
            <li></li>
            <!-- Microsoft-only-konton har inget lösenord att byta. -->
            <li v-if="user.hasPassword !== false">
              <button data-testid="menu-change-password" @click="openChangePassword">
                <span class="mdi mdi-key"></span> Byt lösenord
              </button>
            </li>
            <li>
              <button data-testid="menu-logout" @click="onLogoutFromMenu">
                <span class="mdi mdi-logout"></span> Logga ut
              </button>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <div v-if="store.error.value" class="alert alert-error mb-4">
      <span>{{ store.error.value }}</span>
      <button class="btn btn-sm" @click="reload">Försök igen</button>
    </div>

    <template v-if="page === 'admin'">
      <h2 class="text-xl font-semibold mb-4">Hantera personer & grupper</h2>
      <AdminPanel
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
    </template>

    <template v-else>
      <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
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
            :can-add-for="canWriteFor"
            @edit-vacation="openEditVacation"
            @add-vacation="openNewVacation"
          />
        </div>
      </div>

      <VacationModal
        v-model="modalOpen"
        :vacation="editing"
        :person-name="editingName"
        :start-date="newStartDate"
        :error="vacationError"
        @save="onSaveVacation"
        @delete="onDeleteVacation"
      />
    </template>

    <ChangePasswordModal
      v-model="changePasswordOpen"
      :error="changePasswordError"
      :success="changePasswordSuccess"
      @submit="onChangePassword"
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
import {
  login, logout, currentUser, changePassword, loginOptions, ssoErrorMessage, splitSsoError,
} from './lib/auth.js';
import LoginView from './components/LoginView.vue';
import { useHashRoute } from './composables/useHashRoute.js';
import ChangePasswordModal from './components/ChangePasswordModal.vue';
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
  changePasswordOpen.value = false;
  // Semesterdialogen ligger i den inloggade vyn och monteras om vid nästa
  // inloggning: utan nollställning öppnades förra användarens dialog igen,
  // t.ex. "Lägg till semester — Bo" för en icke-admin efter en 401.
  modalOpen.value = false;
  editing.value = null;
  newPersonId.value = null;
  newStartDate.value = '';
  vacationError.value = null;
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

// Adminsidan visas bara för administratörer; en vanlig användare som når
// #/admin får kalendern. Servern nekar ändå alla adminanrop med 403.
const route = useHashRoute();
const page = computed(() => (route.value === '/admin' && isAdmin.value ? 'admin' : 'calendar'));

function closeMenu() {
  document.activeElement?.blur();
}

const range = ref(defaultRange(new Date()));
const groupFilterId = ref('all');

const modalOpen = ref(false);
const editing = ref(null);
// Vem en ny semester skrivs åt och vilken dag den börjar; satt när
// modalen öppnas för en ny semester (via knappen eller en klickad dag).
const newPersonId = ref(null);
const newStartDate = ref('');

// Samma regel som servern: en vanlig användare skriver bara sin egen
// semester, en administratör allas. Servern svarar annars 403, så en dialog
// som bara kan sluta i det felet öppnas inte.
function canWriteFor(personId) {
  return isAdmin.value || personId === store.me.value?.id;
}

// Namnet i modalens rubrik följer den vars semester det gäller, inte den
// inloggade: en administratör kan skriva någon annans semester och måste
// se vems den är.
const editingName = computed(() => {
  const ownerId = editing.value ? editing.value.personId : newPersonId.value;
  const owner = store.persons.value.find((p) => p.id === ownerId);
  return owner?.name ?? myName.value;
});

function reload() {
  const { from, to } = monthRangeToDates(range.value);
  return store.refresh(from, to);
}

const microsoftLogin = ref(false);

function takeSsoError() {
  const { code, url } = splitSsoError(window.location.href);
  if (code === null) return;

  window.history.replaceState(null, '', url);
  loginError.value = ssoErrorMessage(code);
}

onMounted(async () => {
  takeSsoError();
  try {
    user.value = await currentUser(api);
  } catch {
    user.value = null;
  }
  // Hämtas även för en inloggad användare: knappen behövs efter utloggning.
  const [options] = await Promise.all([loginOptions(api), user.value ? reload() : null]);
  microsoftLogin.value = options.microsoft;
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

async function onLogoutFromMenu() {
  closeMenu();
  await onLogout();
}

const changePasswordOpen = ref(false);
const changePasswordError = ref(null);
const changePasswordSuccess = ref(false);

// Nollställer utfallet från förra bytet: annars visas en gammal bekräftelse,
// och success-propen går inte från false till true vid nästa lyckade byte.
function openChangePassword() {
  closeMenu();
  changePasswordError.value = null;
  changePasswordSuccess.value = false;
  changePasswordOpen.value = true;
}

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

// Knappen "Lägg till semester" skickar inget: då gäller det den inloggade
// och inget förvalt datum. En klickad dag i kalendern skickar person och dag.
function openNewVacation({ personId, date } = {}) {
  const ownerId = personId ?? store.me.value?.id;
  if (!canWriteFor(ownerId)) return;
  editing.value = null;
  newPersonId.value = ownerId;
  newStartDate.value = date ?? '';
  vacationError.value = null;
  modalOpen.value = true;
}
function openEditVacation({ vacation }) {
  if (!canWriteFor(vacation.personId)) return;
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
    else await store.addVacation(newPersonId.value, start, end);
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
