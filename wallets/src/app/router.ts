import { createRouter, createWebHistory } from 'vue-router'
import { session } from './services'
import PageView from '../presentation/PageView.vue'
import Login from '../presentation/Login.vue'
export const vueRouter = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', component: Login },
    { path: '/auth/callback', component: Login },
    { path: '/:pathMatch(.*)*', component: PageView },
  ],
  scrollBehavior: () => ({ top: 0 }),
})
export const authError = { message: '' }
let ready: Promise<void> | undefined
vueRouter.beforeEach(async (to) => {
  ready ??= (async () => {
    try {
      const url = new URL(window.location.href)
      if (url.pathname === '/auth/callback') await session.completeLogin(url)
      else await session.restore()
    } catch (error) {
      authError.message =
        error instanceof Error ? error.message : 'Không thể đăng nhập.'
    }
  })()
  await ready
  if (!session.state.user && to.path !== '/login') return '/login'
  if (session.state.user && ['/login', '/auth/callback'].includes(to.path))
    return '/'
})
