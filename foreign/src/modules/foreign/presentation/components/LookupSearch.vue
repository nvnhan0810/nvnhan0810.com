<script setup lang="ts">
defineProps<{ busy: boolean }>()
const word = defineModel<string>({ required: true })
defineEmits<{ search: []; clear: [] }>()
</script>
<template>
  <form class="lookup-search" role="search" @submit.prevent="$emit('search')">
    <div class="form-group input-with-clear">
      <label class="sr-only" for="lookup-word">Word</label>
      <input
        id="lookup-word"
        v-model="word"
        class="form-control"
        placeholder="Type or paste a word..."
        maxlength="100"
        autocomplete="off"
        autocapitalize="none"
        enterkeyhint="search"
        spellcheck="false"
        required
      />
      <button
        v-if="word"
        class="input-clear"
        type="button"
        aria-label="Clear"
        @click="$emit('clear')"
      >
        ×
      </button>
    </div>
    <button class="btn btn-block" type="submit" :disabled="busy">
      {{ busy ? 'Looking up…' : 'Look up' }}
    </button>
  </form>
</template>
