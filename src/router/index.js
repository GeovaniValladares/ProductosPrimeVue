import { createRouter, createWebHistory } from 'vue-router'
import LoginAuth from '@/views/auth/LoginAuth.vue'
import DashboardView from '@/views/dashboardView.vue'
import { useAuthService } from '@/service/api'
const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', redirect: '/dashboard' },
    { path: '/login', name: 'login', component: LoginAuth },
    {
      path: '/dashboard',
      name: 'dashboard',
      component: DashboardView,
      meta: { requiresAuth: true },
    },
    { path: '/:pathMatch(.*)*', component: () => import('@/views/NotFound.vue') },
  ],
})
router.beforeEach(async (to) => {
  const auth = useAuthService()
  if (to.meta.requiresAuth) {
    if (!auth.isAuthenticated()) return '/login'
    try {
      await auth.getCurrentUser()
    } catch {
      return '/login'
    }
  }
  if (to.name === 'login' && auth.isAuthenticated()) return '/dashboard'
})
export default router
