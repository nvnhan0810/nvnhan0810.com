import { computed, inject, onMounted, ref, shallowRef, watch } from 'vue'
import type { Locale, Portfolio } from '../../domain/entities/portfolio'
import { portfolioKey } from '../portfolio-context'

export function usePortfolio() {
  const dependency = inject(portfolioKey)
  if (!dependency) throw new Error('Portfolio use case has not been provided.')
  const getPortfolio = dependency

  const portfolio = shallowRef<Portfolio | null>(null)
  const locale = ref<Locale>('en')
  const loading = ref(false)
  const error = ref<string | null>(null)
  const messages = computed(() => portfolio.value?.messages[locale.value])

  async function load(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      portfolio.value = await getPortfolio.execute()
    } catch {
      error.value = 'Unable to load this portfolio. Please try again.'
    } finally {
      loading.value = false
    }
  }

  watch(locale, (value) => {
    document.documentElement.lang = value
    try {
      localStorage.setItem('portfolio-locale', value)
    } catch {
      /* Storage can be disabled. */
    }
  })

  watch(messages, (value) => {
    if (!value) return
    document.title = `${value.cv.name} — ${value.cv.title}`
    document.querySelector('meta[name="description"]')?.setAttribute('content', value.cv.summary)
  })

  onMounted(() => {
    try {
      const saved = localStorage.getItem('portfolio-locale')
      if (saved === 'en' || saved === 'vi') locale.value = saved
    } catch {
      /* The default locale works without storage. */
    }
    void load()
  })

  return { portfolio, locale, messages, loading, error, retry: load }
}
