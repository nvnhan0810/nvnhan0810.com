import { RoutePath } from './paths'
import { createRouter, createWebHistory } from 'vue-router'
import YoutubeChannelsPage from '../../modules/foreign/presentation/pages/YoutubeChannelsPage.vue'
import PageView from '../../legacy/application/PageView.vue'
import { sessionDestination } from '../../modules/foreign/application/use-cases/initialize-session'
import LoginPage from '../../modules/foreign/presentation/pages/LoginPage.vue'
import { ensureSession } from '../auth'
import { session } from '../services'

const NavigationOnly = { render: () => null }

export { RoutePath } from './paths'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: RoutePath.index, redirect: RoutePath.lookup },
    { path: RoutePath.login, name: 'login', component: LoginPage },
    { path: RoutePath.authCallback, component: NavigationOnly },
    { path: RoutePath.lookup, name: 'lookup', component: PageView },
    { path: RoutePath.related, name: 'related', component: PageView },
    { path: RoutePath.vocabulary, name: 'vocabulary', component: PageView },
    {
      path: RoutePath.vocabularyDetail,
      name: 'vocabulary-detail',
      component: PageView,
    },
    { path: RoutePath.games, name: 'games', component: PageView },
    { path: RoutePath.puzzle, redirect: RoutePath.games },
    { path: RoutePath.quiz, name: 'quiz', component: PageView },
    ...Object.entries({
      scramble: RoutePath.scramble,
      wordle: RoutePath.wordle,
      hangman: RoutePath.hangman,
      'word-search': RoutePath.wordSearch,
    }).map(([name, path]) => ({
      path,
      name,
      component: PageView,
    })),
    {
      path: RoutePath.channels,
      name: 'youtube-channels',
      component: YoutubeChannelsPage,
    },
    { path: RoutePath.media, name: 'media', component: PageView },
    { path: RoutePath.mediaDetail, name: 'media-detail', component: PageView },
    {
      path: RoutePath.transcriptEdit,
      name: 'transcript',
      component: PageView,
    },
    { path: RoutePath.profile, name: 'profile', component: PageView },
    { path: RoutePath.notFound, redirect: RoutePath.index },
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
