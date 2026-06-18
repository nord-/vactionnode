<template>
  <div class="collapse collapse-arrow border border-base-300 rounded mb-4">
    <input type="checkbox" />
    <div class="collapse-title font-semibold">Hantera personer & grupper</div>
    <div class="collapse-content">
      <div class="grid md:grid-cols-2 gap-6">
        <!-- Grupper -->
        <div>
          <h3 class="font-bold mb-2">Grupper</h3>
          <ul class="flex flex-col gap-1 mb-3">
            <li v-for="g in groups" :key="g.id" class="flex items-center gap-2">
              <input
                class="input input-bordered input-sm flex-1"
                :value="g.name"
                @change="$emit('rename-group', { id: g.id, name: $event.target.value })"
              />
              <button class="btn btn-ghost btn-sm" @click="$emit('remove-group', g.id)">
                <span class="mdi mdi-delete"></span>
              </button>
            </li>
          </ul>
          <div class="flex gap-2">
            <input data-testid="new-group" class="input input-bordered input-sm flex-1" placeholder="Ny grupp" v-model="newGroup" />
            <button data-testid="add-group" class="btn btn-primary btn-sm" :disabled="!newGroup.trim()" @click="addGroup">
              Lägg till
            </button>
          </div>
        </div>

        <!-- Personer -->
        <div>
          <h3 class="font-bold mb-2">Personer</h3>
          <ul class="flex flex-col gap-1 mb-3">
            <li v-for="p in persons" :key="p.id" class="flex items-center gap-2">
              <span class="flex-1">{{ p.name }}</span>
              <select
                class="select select-bordered select-sm"
                :value="p.groupId ?? ''"
                @change="$emit('set-person-group', { id: p.id, groupId: $event.target.value || null })"
              >
                <option value="">Ogrupperade</option>
                <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
              </select>
              <button
                :data-testid="`remove-person-${p.id}`"
                class="btn btn-ghost btn-sm"
                @click="removePerson(p.id)"
              >
                <span class="mdi mdi-delete"></span>
              </button>
            </li>
          </ul>
          <div class="flex gap-2">
            <input data-testid="new-person" class="input input-bordered input-sm flex-1" placeholder="Ny person" v-model="newPerson" />
            <select data-testid="new-person-group" class="select select-bordered select-sm" v-model="newPersonGroup">
              <option value="">Ogrupperade</option>
              <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>
            <button data-testid="add-person" class="btn btn-primary btn-sm" :disabled="!newPerson.trim()" @click="addPerson">
              Lägg till
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({ groups: { type: Array, required: true }, persons: { type: Array, required: true } });
const emit = defineEmits([
  'add-group', 'rename-group', 'remove-group',
  'add-person', 'set-person-group', 'remove-person',
]);

const newGroup = ref('');
const newPerson = ref('');
const newPersonGroup = ref('');

function addGroup() {
  emit('add-group', newGroup.value.trim());
  newGroup.value = '';
}
function addPerson() {
  emit('add-person', { name: newPerson.value.trim(), groupId: newPersonGroup.value || null });
  newPerson.value = '';
  newPersonGroup.value = '';
}
function removePerson(id) {
  if (confirm('Ta bort personen och alla dess registrerade semestrar?')) {
    emit('remove-person', id);
  }
}
</script>
