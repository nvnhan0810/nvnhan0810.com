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
  return {
    status: 200,
    html,
    title: escapeHtml(`${cv.name} — ${cv.title}`),
    description: escapeHtml(cv.summary),
    locale,
    state: `<script id="portfolio-state" type="application/json">${serializeState(state)}</script>`,
  }
}
