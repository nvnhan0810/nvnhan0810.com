import { createRouter, createWebHistory } from 'vue-router'
import TopPage from '../../modules/foreign/presentation/pages/TopPage.vue'
import ProfilePage from '../../modules/foreign/presentation/pages/ProfilePage.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'top', component: TopPage },
    { path: '/auth/callback', name: 'callback', component: TopPage },
    { path: '/profile', name: 'profile', component: ProfilePage },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})
