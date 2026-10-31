import { createRouter, createWebHistory } from 'vue-router'
import { session } from '../services'
import { ensureSession } from '../auth'
import { sessionDestination } from '../../modules/foreign/application/use-cases/initialize-session'
import LoginPage from '../../modules/foreign/presentation/pages/LoginPage.vue'
import LookupPage from '../../modules/foreign/presentation/pages/LookupPage.vue'
import ProfilePage from '../../modules/foreign/presentation/pages/ProfilePage.vue'
const NavigationOnly = { render: () => null }
export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: NavigationOnly },
    { path: '/login', name: 'login', component: LoginPage },
    { path: '/lookup', name: 'lookup', component: LookupPage },
    { path: '/auth/callback', component: NavigationOnly },
    { path: '/profile', name: 'profile', component: ProfilePage },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})
let first = true
router.beforeEach(async (to) => {
  const landing = await ensureSession()
  if (first) {
    first = false
    if (landing && landing !== to.path) return landing
  }
  return sessionDestination(to.path, !!session.state.user) ?? true
})
