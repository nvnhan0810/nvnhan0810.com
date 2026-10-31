<script setup lang="ts">
import WordActions from './WordActions.vue'
import { computed, onUnmounted, ref } from 'vue'
import type { DictionaryEntry } from '../../domain/dictionary'
import { audioUrl } from '../../domain/dictionary'
const props = defineProps<{ entry: DictionaryEntry; lookupActions?: boolean }>()
defineEmits<{ related: [word: string] }>()
const mode = ref<'ui' | 'json'>('ui')
const error = ref('')
const pronunciation = computed(() =>
  props.entry.phonetics.map((p) => audioUrl(p.audio)).find(Boolean),
)
let audio: HTMLAudioElement | undefined
async function pronounce() {
  error.value = ''
  audio?.pause()
  if (!pronunciation.value) return
  audio = new Audio(pronunciation.value)
  try {
    await audio.play()
  } catch {
    error.value = 'Could not play pronunciation.'
  }
}
onUnmounted(() => audio?.pause())
function unique(words: string[]) {
  return [...new Set(words.filter(Boolean))]
}
</script>
<template>
  <article class="card lookup-result-card">
    <div class="lookup-word-row">
      <h2 class="card-title">{{ entry.word }}</h2>
      <button
        v-if="pronunciation"
        class="btn btn-secondary btn-sm"
        aria-label="Pronounce"
        @click="pronounce"
      >
        🔊
      </button>
    </div>
    <p class="card-subtitle lookup-phonetic">
      {{ entry.phonetic || entry.phonetics.find((p) => p.text)?.text }}
    </p>
    <p v-if="error" role="alert">{{ error }}</p>
    <WordActions v-if="lookupActions" :entry="entry" />
    <div class="meanings-header">
      <div class="meanings-mode-toggle" role="group" aria-label="Meanings view">
        <button
          class="btn btn-sm"
          :class="{ 'btn-secondary': mode !== 'ui' }"
          :aria-pressed="mode === 'ui'"
          @click="mode = 'ui'"
        >
          UI</button
        ><button
          class="btn btn-sm"
          :class="{ 'btn-secondary': mode !== 'json' }"
          :aria-pressed="mode === 'json'"
          @click="mode = 'json'"
        >
          JSON
        </button>
      </div>
    </div>
    <pre
      v-if="mode === 'json'"
      class="lookup-json"
      aria-label="Dictionary JSON"
      >{{ JSON.stringify(entry.meanings, null, 2) }}</pre>
    <div v-else class="dict-entry">
      <section
        v-for="(meaning, mi) in entry.meanings"
        :key="mi"
        class="meaning-block"
      >
        <span class="pos-tag">{{ meaning.partOfSpeech }}</span>
        <div
          v-for="(definition, di) in meaning.definitions"
          :key="di"
          class="definition-block"
        >
          <p>{{ definition.definition }}</p>
          <p
            v-for="example in unique(
              definition.examples?.length
                ? definition.examples
                : [definition.example],
            )"
            :key="example"
            class="example muted"
          >
            “{{ example }}”
          </p>
          <div
            v-for="group in [
              { label: 'Synonyms', words: definition.synonyms },
              { label: 'Antonyms', words: definition.antonyms },
            ]"
            :key="group.label"
          >
            <div v-if="group.words.length" class="related-group">
              <div class="related-label">{{ group.label }}</div>
              <div class="related-words">
                <button
                  v-for="word in unique(group.words)"
                  :key="word"
                  class="related-word"
                  @click="$emit('related', word)"
                >
                  {{ word }}
                </button>
              </div>
            </div>
          </div>
        </div>
        <div
          v-for="group in [
            { label: 'Synonyms', words: meaning.synonyms },
            { label: 'Antonyms', words: meaning.antonyms },
          ]"
          :key="group.label"
        >
          <div v-if="group.words.length" class="related-group">
            <div class="related-label">{{ group.label }}</div>
            <div class="related-words">
              <button
                v-for="word in unique(group.words)"
                :key="word"
                class="related-word"
                @click="$emit('related', word)"
              >
                {{ word }}
              </button>
            </div>
          </div>
        </div>
      </section>
    </div>
  </article>
</template>
