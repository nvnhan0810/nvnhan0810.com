const THEME_STORAGE_KEY = 'color-theme'

export type ColorTheme = 'light' | 'dark'

/** Same values as --color-app-bg in styles.css. */
const THEME_COLORS: Record<ColorTheme, string> = {
  light: '#f4f4f5',
  dark: '#0f172a',
}

export function readStoredTheme(): ColorTheme | null {
  const raw = localStorage.getItem(THEME_STORAGE_KEY)
  if (raw === 'light' || raw === 'dark') {
    return raw
  }
  return null
}

export function applyTheme(theme: ColorTheme): void {
  const root = document.documentElement
  if (theme === 'dark') {
    root.classList.add('dark')
  } else {
    root.classList.remove('dark')
  }
  localStorage.setItem(THEME_STORAGE_KEY, theme)
  // Match the browser / installed-app status bar to the chosen theme.
  for (const meta of document.querySelectorAll<HTMLMetaElement>(
    'meta[name="theme-color"]',
  )) {
    meta.content = THEME_COLORS[theme]
    meta.removeAttribute('media')
  }
}

export function toggleTheme(): ColorTheme {
  const root = document.documentElement
  const next: ColorTheme = root.classList.contains('dark') ? 'light' : 'dark'
  applyTheme(next)
  return next
}
