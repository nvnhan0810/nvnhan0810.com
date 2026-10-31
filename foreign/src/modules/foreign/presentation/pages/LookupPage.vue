<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Search, BookOpen, ArrowUpRight } from 'lucide-vue-next'
import type { DictionaryEntry } from '../../domain/dictionary'
import { audioUrl } from '../../domain/dictionary'
import { lookupWord } from '../../infrastructure/dictionary-api'
import { ApiError } from '../../infrastructure/transport'
const route = useRoute()
const router = useRouter()
const query = ref('')
const entries = ref<DictionaryEntry[]>([])
const busy = ref(false)
const error = ref('')
const searched = ref('')
let controller: AbortController | undefined
const suggestions = ['serendipity', 'resilient', 'curiosity']
const message = computed(() =>
  busy.value
    ? `Đang tra “${searched.value}”…`
    : error.value ||
      (entries.value.length ? `Tìm thấy ${entries.value.length} mục từ.` : ''),
)
async function search(word = query.value) {
  const q = word.trim().toLowerCase()
  query.value = q
  if (!q || q.length > 100 || !/^[a-z '\-]+$/.test(q)) {
    error.value = 'Nhập từ hoặc cụm từ tiếng Anh, tối đa 100 ký tự.'
    return
  }
  if (route.query.q === q) await load(q)
  else await router.push({ path: '/lookup', query: { q } })
}
async function load(word: string) {
  controller?.abort()
  const request = new AbortController()
  controller = request
  query.value = word
  searched.value = word
  entries.value = []
  error.value = ''
  if (!word) {
    busy.value = false
    return
  }
  if (word.length > 100 || !/^[a-zA-Z '\-]+$/.test(word)) {
    error.value = 'Nhập từ hoặc cụm từ tiếng Anh, tối đa 100 ký tự.'
    busy.value = false
    return
  }
  busy.value = true
  try {
    const result = await lookupWord(word, request.signal)
    if (controller === request) entries.value = result
  } catch (e) {
    if (controller !== request) return
    error.value =
      e instanceof ApiError && e.code === 'word_not_found'
        ? 'Chưa tìm thấy từ này. Hãy kiểm tra chính tả hoặc thử một từ khác.'
        : e instanceof ApiError && e.code === 'invalid_word'
          ? 'Từ tra cứu không hợp lệ. Hãy thử một từ tiếng Anh.'
          : 'Chưa kết nối được từ điển. Vui lòng thử lại sau.'
  } finally {
    if (controller === request) busy.value = false
  }
}
watch(
  () => route.query.q,
  (q) => {
    void load(typeof q === 'string' ? q.trim() : '')
  },
  { immediate: true },
)
onUnmounted(() => {
  controller?.abort()
  controller = undefined
})
function pronunciation(entry: DictionaryEntry) {
  return [
    ...new Set(
      entry.phonetics
        .map((p) => audioUrl(p.audio))
        .filter((url): url is string => !!url),
    ),
  ]
}
function unique(words: string[]) {
  return [...new Set(words.filter(Boolean))].slice(0, 20)
}
</script>

<template>
  <main class="lookup-page">
    <header class="lookup-heading">
      <p class="eyebrow">MỖI NGÀY, MỘT TỪ MỚI</p>
      <h1>Hiểu một từ.<br /><span>Mở thêm một thế giới.</span></h1>
      <p class="lookup-muted">
        Tra nghĩa, khám phá cách dùng và nghe phát âm tiếng Anh.
      </p>
    </header>
    <form class="lookup-search" role="search" @submit.prevent="search()">
      <Search :size="22" aria-hidden="true" />
      <label class="sr-only" for="lookup-word">Từ tiếng Anh cần tra</label>
      <input
        id="lookup-word"
        v-model="query"
        type="search"
        placeholder="Bạn muốn khám phá từ nào?"
        maxlength="100"
        autocomplete="off"
        autocapitalize="none"
        spellcheck="false"
        required
      />
      <button type="submit">
        Tra từ <ArrowUpRight :size="17" aria-hidden="true" />
      </button>
    </form>
    <div class="lookup-suggestions">
      <span>Thử một từ</span>
      <button v-for="word in suggestions" :key="word" @click="search(word)">
        {{ word }}
      </button>
    </div>
    <p
      class="lookup-status"
      :class="{ 'lookup-error': error }"
      role="status"
      aria-live="polite"
    >
      {{ message }}
    </p>
    <section
      v-if="entries.length"
      class="lookup-results"
      :aria-label="`Kết quả cho ${searched}`"
      :aria-busy="busy"
    >
      <article
        v-for="(entry, index) in entries"
        :key="`${searched}-${index}`"
        class="dictionary-card"
      >
        <header class="dictionary-title">
          <div>
            <p class="eyebrow">ENGLISH DICTIONARY</p>
            <h2>{{ entry.word }}</h2>
            <p class="lookup-phonetic">
              {{ entry.phonetic || entry.phonetics.find((p) => p.text)?.text }}
            </p>
          </div>
          <div v-if="pronunciation(entry).length" class="lookup-audio">
            <span>Nghe phát âm</span>
            <audio
              v-for="url in pronunciation(entry)"
              :key="url"
              :src="url"
              controls
              preload="none"
              :aria-label="`Phát âm ${entry.word}`"
            />
          </div>
        </header>
        <section
          v-for="(meaning, mi) in entry.meanings"
          :key="mi"
          class="lookup-meaning"
        >
          <h3>{{ meaning.partOfSpeech }}</h3>
          <ol>
            <li v-for="(definition, di) in meaning.definitions" :key="di">
              <p>{{ definition.definition }}</p>
              <blockquote v-if="definition.example">
                {{ definition.example }}
              </blockquote>
              <div
                v-for="group in [
                  { label: 'Đồng nghĩa', words: definition.synonyms },
                  { label: 'Trái nghĩa', words: definition.antonyms },
                ]"
                :key="group.label"
              >
                <div v-if="group.words.length" class="lookup-related">
                  <span>{{ group.label }}</span
                  ><button
                    v-for="word in unique(group.words)"
                    :key="word"
                    @click="search(word)"
                  >
                    {{ word }}
                  </button>
                </div>
              </div>
            </li>
          </ol>
          <div
            v-for="group in [
              { label: 'Đồng nghĩa', words: meaning.synonyms },
              { label: 'Trái nghĩa', words: meaning.antonyms },
            ]"
            :key="group.label"
          >
            <div v-if="group.words.length" class="lookup-related">
              <span>{{ group.label }}</span
              ><button
                v-for="word in unique(group.words)"
                :key="word"
                @click="search(word)"
              >
                {{ word }}
              </button>
            </div>
          </div>
        </section>
      </article>
      <p class="lookup-source">
        Nguồn:
        <a
          href="https://dictionaryapi.dev/"
          target="_blank"
          rel="noopener noreferrer"
          >Free Dictionary API ↗</a
        >
        · Định nghĩa bằng tiếng Anh.
      </p>
    </section>
    <section v-else-if="!busy && !error" class="lookup-empty">
      <BookOpen :size="32" aria-hidden="true" />
      <h2>Bắt đầu bằng sự tò mò.</h2>
      <p>Nhập một từ để xem nghĩa, ví dụ và cách phát âm.</p>
    </section>
    <button
      v-if="error && searched"
      class="lookup-retry"
      @click="search(searched)"
    >
      Thử lại
    </button>
  </main>
</template>
