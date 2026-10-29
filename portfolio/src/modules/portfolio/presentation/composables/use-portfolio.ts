import { computed, inject, onMounted, ref, shallowRef, watch } from 'vue'
import type { Locale, Portfolio } from '../../domain/entities/portfolio'
import { portfolioKey, portfolioStateKey } from '../portfolio-context'

export function usePortfolio() {
  const dependency = inject(portfolioKey)
  if (!dependency) throw new Error('Portfolio use case has not been provided.')
  const getPortfolio = dependency

  const initialState = inject(portfolioStateKey, null)
  const portfolio = shallowRef<Portfolio | null>(initialState?.portfolio ?? null)
  const locale = ref<Locale>(initialState?.locale ?? 'en')
  const year = initialState?.year ?? new Date().getFullYear()
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

  function persistLocale(): void {
    document.documentElement.lang = locale.value
    const url = new URL(location.href)
    if (url.searchParams.has('lang') && url.searchParams.get('lang') !== locale.value) {
      url.searchParams.set('lang', locale.value)
      history.replaceState(history.state, '', url)
    }
    try {
      document.cookie = `portfolio-locale=${locale.value}; Path=/; Max-Age=31536000; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`
      localStorage.setItem('portfolio-locale', locale.value)
    } catch {
      /* Storage can be disabled. */
    }
  }

  function updateMetadata(): void {
    const value = messages.value
    if (!value) return
    document.title = `${value.cv.name} — ${value.cv.title}`
    document.querySelector('meta[name="description"]')?.setAttribute('content', value.cv.summary)
  }

  watch(locale, persistLocale)
  watch(messages, updateMetadata)

  onMounted(() => {
    // Hydrate with the server's locale first. Storage is only a CSR/test fallback.
    try {
      const saved = localStorage.getItem('portfolio-locale')
      if (!initialState && (saved === 'en' || saved === 'vi')) locale.value = saved
    } catch {
      /* The default locale works without storage. */
    }
    persistLocale()
    updateMetadata()
    if (!portfolio.value) void load()
  })

  return { portfolio, locale, messages, year, loading, error, retry: load }
}
