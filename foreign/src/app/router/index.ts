import { createRouter, createWebHistory } from 'vue-router'
import TopPage from '../../modules/foreign/presentation/pages/TopPage.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [{ path: '/', name: 'top', component: TopPage }],
})
