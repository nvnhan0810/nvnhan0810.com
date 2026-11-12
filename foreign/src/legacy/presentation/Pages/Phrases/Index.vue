<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link } from '@/application/navigation'
import AppLayout from '@/Layouts/AppLayout.vue'
import type { PhraseEntry } from '@/domain/meanings-json'
import { playPronunciation } from '@/infrastructure/pronunciation'
import { RoutePath } from '../../../../app/router/paths'

const props = withDefaults(defineProps<{ items?: PhraseEntry[] }>(), {
  items: () => [],
})
const query = ref('')
const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return props.items
  return props.items.filter((item) =>
    `${item.word} ${item.phonetic || ''} ${item.meanings.map((m) => m.definition).join(' ')}`
      .toLowerCase()
      .includes(q),
  )
})

function preview(item: PhraseEntry) {
  const definition = item.meanings[0]?.definition || ''
  return definition.length > 100 ? `${definition.slice(0, 100)}…` : definition
}

async function pronounce(item: PhraseEntry) {
  await playPronunciation({ word: item.word, audioUrl: item.audio_url })
}
</script>
<template>
  <Head title="Phrases" />
  <AppLayout title="Phrases" heading="Phrases">
    <div v-if="!items.length" class="empty-state">
      No phrases saved yet. Search a phrase or idiom in Lookup, use Copy prompt,
      then paste the AI JSON and Save. No linked word is required.
    </div>
    <template v-else>
      <div class="form-group" style="margin-top: 0">
        <div class="input-with-clear">
          <input
            id="phrase-search"
            v-model="query"
            type="search"
            class="form-control"
            placeholder="Search saved phrases..."
            aria-label="Search phrases"
            autocomplete="off"
            enterkeyhint="search"
          />
          <button
            v-show="query"
            type="button"
            class="input-clear"
            aria-label="Clear"
            @click="query = ''"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="18"
              height="18"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path
                fill="currentColor"
                d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"
              />
            </svg>
          </button>
        </div>
      </div>

      <div data-vocab-list>
        <article
          v-for="item in filtered"
          :key="item.word"
          class="card vocab-card"
        >
          <div class="vocab-card-row">
            <Link
              :href="`${RoutePath.lookup}?q=${encodeURIComponent(item.word)}&kind=phrase`"
              class="vocab-card-link"
            >
              <p class="card-title">{{ item.word }}</p>
              <p v-if="item.phonetic" class="card-subtitle vocab-card-phonetic">
                {{ item.phonetic }}
              </p>
              <p v-if="preview(item)" class="vocab-card-preview">
                {{ preview(item) }}
              </p>
              <span class="vocab-card-hint">View details ›</span>
            </Link>
            <div class="vocab-card-actions">
              <button
                type="button"
                class="btn-icon"
                title="Play pronunciation"
                aria-label="Play pronunciation"
                @click="pronounce(item)"
              >
                🔊
              </button>
            </div>
          </div>
        </article>
      </div>

      <div v-show="!filtered.length" class="empty-state vocab-search-empty">
        No matching phrases found.
      </div>
    </template>
  </AppLayout>
</template>
