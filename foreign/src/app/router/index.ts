import { createRouter, createWebHistory } from 'vue-router'
import TopPage from '../../modules/foreign/presentation/pages/TopPage.vue'
import LookupPage from '../../modules/foreign/presentation/pages/LookupPage.vue'
import ProfilePage from '../../modules/foreign/presentation/pages/ProfilePage.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/lookup' },
    { path: '/lookup', name: 'lookup', component: LookupPage },
    { path: '/auth/callback', name: 'callback', component: TopPage },
    { path: '/profile', name: 'profile', component: ProfilePage },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})
