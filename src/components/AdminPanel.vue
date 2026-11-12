<template>
  <div class="grid lg:grid-cols-3 gap-6 items-start">
    <!-- Grupper -->
    <div class="card bg-base-100 shadow-sm">
      <div class="card-body">
        <h2 class="card-title">Grupper</h2>
        <ul class="flex flex-col gap-1 mb-3">
          <li v-for="g in groups" :key="g.id" class="flex items-center gap-2">
            <input
              class="input input-sm flex-1"
              :value="g.name"
              @change="$emit('rename-group', { id: g.id, name: $event.target.value })"
            />
            <button class="btn btn-ghost btn-sm" @click="$emit('remove-group', g.id)">
              <span class="mdi mdi-delete"></span>
            </button>
          </li>
        </ul>
        <div class="flex gap-2">
          <input data-testid="new-group" class="input input-sm flex-1" placeholder="Ny grupp" v-model="newGroup" />
          <button data-testid="add-group" class="btn btn-primary btn-sm" :disabled="!newGroup.trim()" @click="addGroup">
            Lägg till
          </button>
        </div>
      </div>
    </div>

    <!-- Personer -->
    <div class="card bg-base-100 shadow-sm lg:col-span-2">
      <div class="card-body">
        <h2 class="card-title">Personer</h2>
        <ul class="flex flex-col gap-1 mb-3">
          <li v-for="p in persons" :key="p.id" class="flex items-center gap-2">
            <span class="flex-1">{{ p.name }}</span>
            <select
              class="select select-sm w-auto"
              :data-testid="`person-group-${p.id}`"
              :value="p.groupId ?? ''"
              @change="$emit('set-person-group', { id: p.id, groupId: $event.target.value || null })"
            >
              <option value="">Ogrupperade</option>
              <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>
            <input
              :data-testid="`person-password-${p.id}`"
              type="password"
              class="input input-sm w-auto"
              placeholder="Nytt lösenord"
              v-model="passwordDrafts[p.id]"
            />
            <button
              :data-testid="`set-password-${p.id}`"
              class="btn btn-ghost btn-sm"
              :disabled="(passwordDrafts[p.id] || '').length < 8"
              @click="setPassword(p.id)"
            >
              Spara lösenord
            </button>
            <button
              :data-testid="`remove-person-${p.id}`"
              class="btn btn-ghost btn-sm"
              @click="removePerson(p.id)"
            >
              <span class="mdi mdi-delete"></span>
            </button>
          </li>
        </ul>
        <div class="flex flex-wrap gap-2">
          <input data-testid="new-person" class="input input-sm flex-1" placeholder="Ny person" v-model="newPerson" />
          <input data-testid="new-person-email" type="email" class="input input-sm w-auto"
                 placeholder="E-post" v-model="newPersonEmail" />
          <input data-testid="new-person-password" type="password" class="input input-sm w-auto"
                 placeholder="Lösenord" v-model="newPersonPassword" />
          <label class="label cursor-pointer gap-2">
            <span class="text-sm">Administratör</span>
            <input data-testid="new-person-admin" type="checkbox" class="checkbox checkbox-sm"
                   v-model="newPersonAdmin" />
          </label>
          <select data-testid="new-person-group" class="select select-sm w-auto" v-model="newPersonGroup">
            <option value="">Ogrupperade</option>
            <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
          </select>
          <button data-testid="add-person" class="btn btn-primary btn-sm"
                  :disabled="!newPerson.trim() || !newPersonEmail.trim() || newPersonPassword.length < 8"
                  @click="addPerson">
            Lägg till
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';

defineProps({ groups: { type: Array, required: true }, persons: { type: Array, required: true } });
const emit = defineEmits([
  'add-group', 'rename-group', 'remove-group',
  'add-person', 'set-person-group', 'remove-person', 'set-password',
]);

const newGroup = ref('');
const newPerson = ref('');
const newPersonEmail = ref('');
const newPersonPassword = ref('');
const newPersonAdmin = ref(false);
const newPersonGroup = ref('');
// Nyckel per person-id, inte en delad ref — annars läcker ett halvskrivet
// lösenord i en rad in i en annan när listan renderas om.
const passwordDrafts = reactive({});

function addGroup() {
  emit('add-group', newGroup.value.trim());
  newGroup.value = '';
}
function addPerson() {
  emit('add-person', {
    name: newPerson.value.trim(),
    email: newPersonEmail.value.trim(),
    password: newPersonPassword.value,
    groupId: newPersonGroup.value || null,
    isAdmin: newPersonAdmin.value,
  });
  newPerson.value = '';
  newPersonEmail.value = '';
  newPersonPassword.value = '';
  newPersonAdmin.value = false;
  newPersonGroup.value = '';
}
function removePerson(id) {
  if (confirm('Ta bort personen och alla dess registrerade semestrar?')) {
    emit('remove-person', id);
  }
}
function setPassword(id) {
  emit('set-password', { id, password: passwordDrafts[id] });
  passwordDrafts[id] = '';
}
</script>
