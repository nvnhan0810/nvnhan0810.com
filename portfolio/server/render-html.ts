import type { RenderResult } from './types.ts'

export function resolveLocale(url: URL, cookie: string | undefined): 'en' | 'vi' {
  const requested = url.searchParams.get('lang')
  if (requested === 'en' || requested === 'vi') return requested
  return /(?:^|;\s*)portfolio-locale=vi(?:;|$)/.test(cookie ?? '') ? 'vi' : 'en'
}

export function injectHtml(
  template: string,
  result: Extract<RenderResult, { status: 200 }>,
): string {
  // Replacement callbacks keep literal "$&" and "$'" in content intact.
  return template
    .replace('<!--app-lang-->', () => result.locale)
    .replace('<!--app-title-->', () => result.title)
    .replace('<!--app-description-->', () => result.description)
    .replace('<!--app-html-->', () => result.html)
    .replace('<!--app-state-->', () => result.state)
}
