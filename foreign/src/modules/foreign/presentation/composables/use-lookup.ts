import { onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { DictionaryEntry } from '../../domain/dictionary'
import { normalizeLookupWord } from '../../application/use-cases/lookup-word'
import { lookup } from '../../../../app/lookup'
import { LookupFailure } from '../../domain/lookup-error'
export function useLookup() {
  const route = useRoute()
  const router = useRouter()
  const query = ref('')
  const entries = ref<DictionaryEntry[]>([])
  const busy = ref(false)
  const error = ref('')
  const searched = ref('')
  let controller: AbortController | undefined
  async function load(word: string) {
    controller?.abort()
    const request = new AbortController()
    controller = request
    query.value = searched.value = word
    entries.value = []
    error.value = ''
    busy.value = false
    if (!word) return
    busy.value = true
    try {
      const result = await lookup.execute(word, request.signal)
      if (controller === request) entries.value = result
    } catch (e) {
      if (controller !== request) return
      error.value =
        e instanceof LookupFailure
          ? e.reason === 'not_found'
            ? 'No dictionary entry found. Try another word.'
            : e.reason === 'unauthorized'
              ? 'Session expired. Please sign in again.'
              : 'Dictionary unavailable. Please try again.'
          : e instanceof Error
            ? e.message
            : 'Lookup failed.'
    } finally {
      if (controller === request) busy.value = false
    }
  }
  async function search(word = query.value) {
    try {
      const q = normalizeLookupWord(word)
      if (route.query.q === q) await load(q)
      else await router.push({ path: '/lookup', query: { q } })
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Invalid word.'
    }
  }
  async function clear() {
    query.value = ''
    await router.replace('/lookup')
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
  return { query, entries, busy, error, searched, search, clear }
}
