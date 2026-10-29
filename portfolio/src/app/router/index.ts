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
      {
        path: '/apps',
        name: 'apps',
        component: () => import('@/modules/portfolio/presentation/pages/AppsPage.vue'),
      },
      {
        path: '/apps/:slug',
        name: 'app-detail',
        beforeEnter: (to) =>
          ['foreign-language-course', 'db-management-tool'].includes(String(to.params.slug))
            ? true
            : '/apps',
        component: () => import('@/modules/portfolio/presentation/pages/AppsPage.vue'),
      },
      { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
  })
}
