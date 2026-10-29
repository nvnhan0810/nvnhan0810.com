import { appsMessages as enApps } from './modules/portfolio/infrastructure/data/apps-en'
import { appsMessages as viApps } from './modules/portfolio/infrastructure/data/apps-vi'
import { renderToString } from 'vue/server-renderer'
import { createPortfolioApp } from './main'
import { createDependencies } from './app/bootstrap'
import type { Locale } from './modules/portfolio/domain/entities/portfolio'
import type { PortfolioState } from './modules/portfolio/presentation/portfolio-context'
import type { RenderResult } from '../server/types'
import { escapeHtml, serializeState } from './app/ssr-html'

export async function render(url: string, locale: Locale): Promise<RenderResult> {
  const portfolio = await createDependencies().getPortfolio.execute()
  const state: PortfolioState = { portfolio, locale, year: new Date().getFullYear() }
  const { app, router } = createPortfolioApp(state, true)
  await router.push(url)
  await router.isReady()

  const route = router.currentRoute.value
  if (route.redirectedFrom) return { status: 302, location: route.fullPath }

  const html = await renderToString(app)
  const cv = portfolio.messages[locale].cv
  const apps = (locale === 'vi' ? viApps : enApps).apps
  const isApps = route.path === '/apps' || route.name === 'app-detail'
  const detail = apps.details[String(route.params.slug)]
  return {
    status: 200,
    html,
    title: escapeHtml(
      isApps ? `${detail?.name ?? apps.title} | ${apps.label}` : `${cv.name} — ${cv.title}`,
    ),
    description: escapeHtml(
      isApps ? (detail?.metaDescription ?? apps.metaDescription) : cv.summary,
    ),
    locale,
    state: `<script id="portfolio-state" type="application/json">${serializeState(state)}</script>`,
  }
}
