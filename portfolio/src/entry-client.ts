import { createPortfolioApp } from './main'
import type { PortfolioState } from './modules/portfolio/presentation/portfolio-context'
import './app/styles/main.css'

const element = document.getElementById('portfolio-state')
if (!element?.textContent) throw new Error('SSR portfolio state is missing.')

// This JSON is generated and escaped by our SSR renderer, never by user input.
const state = JSON.parse(element.textContent) as PortfolioState
const { app, router } = createPortfolioApp(state)
await router.isReady()
app.mount('#app')
element.remove()
