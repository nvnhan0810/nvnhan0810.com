import { createApp } from 'vue'
import App from './app/App.vue'
import { router } from './app/router'
import './legacy/presentation/styles/reset.css'
import './legacy/presentation/styles/mobile.css'
import './legacy/presentation/styles/responsive.css'
import './legacy/presentation/styles/desktop.css'
import { initializeTheme } from './shared/theme'

initializeTheme()

createApp(App).use(router).mount('#app')

if (import.meta.env.PROD && 'serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    void navigator.serviceWorker.register('/sw.js').catch(() => {})
  })
}
