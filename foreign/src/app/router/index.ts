import { createRouter, createWebHistory } from 'vue-router'
import { session } from '../services'
import { ensureSession } from '../auth'
import { sessionDestination } from '../../modules/foreign/application/use-cases/initialize-session'
import LoginPage from '../../modules/foreign/presentation/pages/LoginPage.vue'
import PageView from '../../legacy/application/PageView.vue'
const NavigationOnly = { render: () => null }
export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', component: NavigationOnly },
    { path: '/login', name: 'login', component: LoginPage },
    { path: '/auth/callback', component: NavigationOnly },
    {
      path: '/lookup',
      redirect: (to) => ({ path: '/home/lookup', query: to.query }),
    },
    { path: '/vocabulary', redirect: '/home/vocab' },
    {
      path: '/vocabulary/:id',
      redirect: (to) => `/home/vocab/${to.params.id}`,
    },
    { path: '/profile', redirect: '/home/profile' },
    { path: '/home/lookup', name: 'lookup', component: PageView },
    { path: '/home/word/:word', name: 'related', component: PageView },
    { path: '/home/vocab', name: 'vocabulary', component: PageView },
    { path: '/home/vocab/:id', name: 'vocabulary-detail', component: PageView },
    { path: '/home/quiz', name: 'games', component: PageView },
    { path: '/home/puzzle', redirect: '/home/quiz' },
    { path: '/home/quiz/play', name: 'quiz', component: PageView },
    ...['scramble', 'wordle', 'hangman', 'word-search'].map((mode) => ({
      path: `/home/puzzle/${mode}`,
      name: mode,
      component: PageView,
    })),
    { path: '/home/media', name: 'media', component: PageView },
    { path: '/home/media/:id', name: 'media-detail', component: PageView },
    {
      path: '/home/media/:id/transcript/edit',
      name: 'transcript',
      component: PageView,
    },
    { path: '/home/profile', name: 'profile', component: PageView },
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
