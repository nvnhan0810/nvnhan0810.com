import { createRouter, createWebHistory } from 'vue-router'

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'portfolio',
      component: () => import('@/modules/portfolio/presentation/pages/PortfolioPage.vue'),
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})
