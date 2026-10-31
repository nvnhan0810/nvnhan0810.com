import { onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { SavedWord } from '../../domain/vocabulary'
import { listMyWords } from '../../../../app/vocabulary'
export function useMyWords() {
  const route = useRoute(),
    router = useRouter()
  const query = ref(''),
    items = ref<SavedWord[]>([]),
    busy = ref(false),
    error = ref(''),
    next = ref<number | null>(null)
  let controller: AbortController | undefined
  async function load(more = false) {
    controller?.abort()
    const request = new AbortController()
    controller = request
    busy.value = true
    error.value = ''
    if (!more) {
      items.value = []
      next.value = null
    }
    try {
      const page = await listMyWords.execute(
        query.value,
        more ? (next.value ?? undefined) : undefined,
        request.signal,
      )
      if (controller !== request) return
      items.value = more ? [...items.value, ...page.items] : page.items
      next.value = page.next_cursor
    } catch {
      if (controller === request)
        error.value = 'Could not load your words. Please try again.'
    } finally {
      if (controller === request) busy.value = false
    }
  }
  async function search() {
    const q = query.value.trim()
    if (route.query.q === q) await load()
    else await router.push({ path: '/vocabulary', query: q ? { q } : {} })
  }
  watch(
    () => route.query.q,
    (q) => {
      query.value = typeof q === 'string' ? q : ''
      void load()
    },
    { immediate: true },
  )
  onUnmounted(() => {
    controller?.abort()
    controller = undefined
  })
  return { query, items, busy, error, next, load, search }
}
