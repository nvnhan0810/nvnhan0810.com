import { createApp } from 'vue'
import App from './app/App.vue'
import { createDependencies } from './app/bootstrap'
import { router } from './app/router'
import { portfolioKey } from './modules/portfolio/presentation/portfolio-context'
import './app/styles/main.css'

const app = createApp(App)
const dependencies = createDependencies()
app.provide(portfolioKey, dependencies.getPortfolio)
app.use(router)
app.mount('#app')
