import { createSSRApp } from 'vue'
import App from './app/App.vue'
import { createDependencies } from './app/bootstrap'
import { createPortfolioRouter } from './app/router'
import type { PortfolioState } from './modules/portfolio/presentation/portfolio-context'
import { portfolioKey, portfolioStateKey } from './modules/portfolio/presentation/portfolio-context'

// Never share a Vue app, router, or mutable state between SSR requests.
export function createPortfolioApp(state: PortfolioState, server = false) {
  const app = createSSRApp(App)
  const router = createPortfolioRouter(server)
  const dependencies = createDependencies()
  app.provide(portfolioKey, dependencies.getPortfolio)
  app.provide(portfolioStateKey, state)
  app.use(router)
  return { app, router }
}
