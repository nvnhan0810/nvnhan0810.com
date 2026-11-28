import { createApp } from 'vue'
import App from './app/App.vue'
import { vueRouter } from './app/router'
import { route } from './application/routes'
import {
  applyTheme,
  readStoredTheme,
} from './infrastructure/theme/theme-storage'
import { registerServiceWorker } from './infrastructure/pwa'
import { initPwaInstall } from './application/pwa-install'
import './styles.css'
Object.assign(globalThis, { route })
applyTheme(
  readStoredTheme() ??
    (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'),
)
const app = createApp(App)
app.config.globalProperties.route = route
app.use(vueRouter).mount('#app')
initPwaInstall()
registerServiceWorker()
