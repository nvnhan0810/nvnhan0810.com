import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './app/App.vue'
import './app/styles.css'
const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'overview',
      component: () => import('./modules/identity/presentation/pages/OverviewPage.vue'),
    },
    {
      path: '/clients',
      name: 'clients',
      component: () => import('./modules/identity/presentation/pages/ClientsPage.vue'),
    },
    {
      path: '/users',
      name: 'users',
      component: () => import('./modules/identity/presentation/pages/UsersPage.vue'),
    },
    {
      path: '/auth/callback',
      name: 'callback',
      component: () => import('./modules/identity/presentation/pages/OverviewPage.vue'),
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})
createApp(App).use(router).mount('#app')
