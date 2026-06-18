<template>
  <div class="min-h-screen flex items-center justify-center bg-base-200">
    <div class="card bg-base-100 shadow-md p-6 w-96">
      <h1 class="text-xl font-bold mb-4">Vem är du?</h1>

      <div v-if="persons.length" class="mb-4">
        <p class="label-text mb-1">Välj befintlig:</p>
        <div class="flex flex-col gap-1 max-h-60 overflow-y-auto">
          <button
            v-for="p in persons"
            :key="p.id"
            :data-testid="`select-${p.id}`"
            class="btn btn-ghost justify-start"
            @click="$emit('select', p.id)"
          >
            {{ p.name }}
          </button>
        </div>
      </div>

      <div class="divider">eller</div>

      <p class="label-text mb-1">Skapa ny:</p>
      <div class="flex gap-2">
        <input
          data-testid="new-name"
          class="input input-bordered flex-1"
          placeholder="Ditt namn"
          v-model="newName"
        />
        <button data-testid="create" class="btn btn-primary" :disabled="!newName.trim()" @click="$emit('create', newName.trim())">
          Skapa
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
defineProps({ persons: { type: Array, required: true } });
defineEmits(['select', 'create']);
const newName = ref('');
</script>
