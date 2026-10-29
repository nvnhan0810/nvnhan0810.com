import { createMemoryHistory, createRouter, createWebHistory } from 'vue-router'

export function createPortfolioRouter(server: boolean) {
  return createRouter({
    history: server
      ? createMemoryHistory(import.meta.env.BASE_URL)
      : createWebHistory(import.meta.env.BASE_URL),
    routes: [
      {
        path: '/',
        name: 'portfolio',
        component: () => import('@/modules/portfolio/presentation/pages/PortfolioPage.vue'),
      },
      { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
  })
}
