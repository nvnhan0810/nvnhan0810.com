<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { loadPage, page } from './navigation'
import Learn from '../presentation/Pages/Learn/Index.vue'
import Vocab from '../presentation/Pages/Vocab/Index.vue'
import VocabShow from '../presentation/Pages/Vocab/Show.vue'
import Hub from '../presentation/Pages/Quiz/Hub.vue'
import Quiz from '../presentation/Pages/Quiz/Play.vue'
import Scramble from '../presentation/Pages/Puzzle/Scramble.vue'
import Wordle from '../presentation/Pages/Puzzle/Wordle.vue'
import Hangman from '../presentation/Pages/Puzzle/Hangman.vue'
import WordSearch from '../presentation/Pages/Puzzle/WordSearch.vue'
import Media from '../presentation/Pages/Media/Index.vue'
import MediaShow from '../presentation/Pages/Media/Show.vue'
import Transcript from '../presentation/Pages/Media/EditTranscript.vue'
import Profile from '../presentation/Pages/Profile/Show.vue'
const route = useRoute()
const busy = ref(true),
  error = ref('')
const components = {
  related: VocabShow,
  lookup: Learn,
  vocabulary: Vocab,
  'vocabulary-detail': VocabShow,
  games: Hub,
  quiz: Quiz,
  scramble: Scramble,
  wordle: Wordle,
  hangman: Hangman,
  'word-search': WordSearch,
  media: Media,
  'media-detail': MediaShow,
  transcript: Transcript,
  profile: Profile,
}
const component = computed(
  () => components[route.name as keyof typeof components],
)
// Shared transport metadata stays in usePage(); pass only declared props to each source screen.
const screenProps = computed(() => {
  const declared =
    (
      component.value as unknown as {
        props?: Record<string, unknown> | readonly string[]
      }
    )?.props ?? {}
  return Object.fromEntries(
    Object.entries(page.props).filter(([key]) =>
      Array.isArray(declared)
        ? declared.includes(key)
        : Object.hasOwn(declared, key),
    ),
  )
})
async function load() {
  const path = route.fullPath
  busy.value = true
  error.value = ''
  try {
    await loadPage(path)
  } catch (e) {
    if (path === route.fullPath)
      error.value = e instanceof Error ? e.message : 'Could not load page.'
  } finally {
    if (path === route.fullPath) busy.value = false
  }
}
watch(() => route.fullPath, load, { immediate: true })
</script>
<template>
  <p v-if="busy" class="empty-state" role="status">Loading…</p>
  <div v-else-if="error" class="empty-state" role="alert">
    {{ error }} <button class="btn" @click="load">Retry</button>
  </div>
  <component
    :is="component"
    v-else
    :key="`${route.path}:${page.revision}`"
    v-bind="screenProps"
  />
</template>
