<script setup lang="ts">
import { ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import type { DictionaryEntry } from '../../domain/dictionary'
import { saveWord } from '../../../../app/vocabulary'
import { copyPrompt } from '../../../../app/clipboard'
const props = withDefaults(
  defineProps<{ entry: DictionaryEntry; allowSave?: boolean }>(),
  { allowSave: true },
)
const saving = ref(false)
const copying = ref(false)
const saved = ref(false)
const copied = ref(false)
const error = ref('')
watch(
  () => props.entry,
  () => {
    saved.value = false
    copied.value = false
    error.value = ''
  },
)
async function save() {
  saving.value = true
  error.value = ''
  try {
    await saveWord.execute(props.entry)
    saved.value = true
  } catch {
    error.value = 'Could not save this word. Please try again.'
  } finally {
    saving.value = false
  }
}
async function copy() {
  copying.value = true
  error.value = ''
  try {
    await copyPrompt.execute(props.entry)
    copied.value = true
  } catch {
    error.value = 'Could not copy. Please allow clipboard access and try again.'
  } finally {
    copying.value = false
  }
}
</script>
<template>
  <div class="word-actions">
    <button class="btn btn-secondary btn-sm" :disabled="copying" @click="copy">
      {{ copied ? 'Copied!' : 'Copy prompt AI' }}
    </button>
    <button
      v-if="allowSave"
      class="btn btn-sm"
      :disabled="saving || saved"
      @click="save"
    >
      {{ saved ? 'Saved' : saving ? 'Saving…' : 'Save word' }}
    </button>
    <RouterLink v-if="saved" to="/vocabulary">Từ của tôi →</RouterLink>
    <p v-if="error" class="alert" role="alert">{{ error }}</p>
    <span class="sr-only" role="status"
      >{{ saved ? 'Word saved.' : '' }}
      {{ copied ? 'Prompt copied.' : '' }}</span
    >
  </div>
</template>
