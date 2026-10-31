import { ref } from 'vue'

type Theme = 'light' | 'dark'
const key = 'flc-theme'
export const theme = ref<Theme>('light')

function apply(value: Theme) {
  theme.value = value
  document.documentElement.dataset.theme = value
  document.documentElement.style.colorScheme = value
  document
    .querySelector('meta[name="theme-color"]')
    ?.setAttribute('content', value === 'dark' ? '#0f1419' : '#f8f9fa')
}

export function initializeTheme() {
  let saved: string | null = null
  try {
    saved = localStorage.getItem(key)
  } catch {
    /* Storage may be unavailable. */
  }
  apply(
    saved === 'dark' || saved === 'light'
      ? saved
      : window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light',
  )
}

export function toggleTheme() {
  const next = theme.value === 'dark' ? 'light' : 'dark'
  apply(next)
  try {
    localStorage.setItem(key, next)
  } catch {
    /* Keep the active theme for this page. */
  }
}
