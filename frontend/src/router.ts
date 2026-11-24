import { createRouter, createWebHistory } from 'vue-router'
import { useSessionStore } from './stores/session'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: () => import('./views/LoginView.vue'), meta: { public: true } },
    { path: '/', name: 'dashboard', component: () => import('./views/DashboardView.vue') },
    { path: '/transactions', name: 'transactions', component: () => import('./views/TransactionsView.vue') },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach((to) => {
  const session = useSessionStore()

  if (!to.meta.public && !session.isAuthenticated) {
    return { name: 'login', query: to.fullPath !== '/' ? { redirect: to.fullPath } : {} }
  }

  if (to.name === 'login' && session.isAuthenticated) {
    return { name: 'dashboard' }
  }
})
