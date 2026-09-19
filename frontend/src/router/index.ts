import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { i18n } from '@/i18n'

/**
 * Views are loaded lazily so the login screen does not ship the dashboard's
 * chart code to a visitor who has not signed in yet.
 */
const router = createRouter({
  // Matches the Vite base: "/" while developing, "/app/" once built and served
  // from the Symfony public directory.
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'dashboard',
      component: () => import('@/views/DashboardView.vue'),
      meta: { requiresAuth: true, titleKey: 'titles.dashboard' },
    },
    {
      path: '/diary',
      name: 'diary',
      component: () => import('@/views/DiaryView.vue'),
      meta: { requiresAuth: true, titleKey: 'titles.diary' },
    },
    {
      path: '/foods',
      name: 'foods',
      component: () => import('@/views/FoodsView.vue'),
      meta: { requiresAuth: true, titleKey: 'titles.foods' },
    },
    {
      path: '/activity',
      name: 'activity',
      component: () => import('@/views/ActivityView.vue'),
      meta: { requiresAuth: true, titleKey: 'titles.activity' },
    },
    {
      path: '/admin/users',
      name: 'admin-users',
      component: () => import('@/views/AdminUsersView.vue'),
      // The guard below turns anybody without the role away. It is a courtesy,
      // not the security boundary - the endpoints this page calls answer 403 on
      // their own, and would do so even if this meta were deleted.
      meta: { requiresAuth: true, requiresRole: 'ROLE_USER_ADMIN', titleKey: 'titles.admin' },
    },
    {
      path: '/profile',
      name: 'profile',
      component: () => import('@/views/ProfileView.vue'),
      meta: { requiresAuth: true, titleKey: 'titles.profile' },
    },
    {
      path: '/settings',
      name: 'settings',
      component: () => import('@/views/SettingsView.vue'),
      meta: { requiresAuth: true, titleKey: 'titles.settings' },
    },
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guestOnly: true, titleKey: 'titles.login' },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/RegisterView.vue'),
      meta: { guestOnly: true, titleKey: 'titles.register' },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: () => import('@/views/ForgotPasswordView.vue'),
      meta: { guestOnly: true, titleKey: 'titles.forgotPassword' },
    },
    {
      // Where the reset link in the e-mail lands, with the token in the query
      // string. Not guestOnly: somebody who is signed in on this device and
      // opens the link from their mail should get the form they asked for, not
      // a silent bounce to the dashboard - the likeliest reason they asked is
      // that they are about to lose access to the session they are in.
      path: '/reset-password',
      name: 'reset-password',
      component: () => import('@/views/ResetPasswordView.vue'),
      meta: { titleKey: 'titles.resetPassword' },
    },
    {
      // Where the confirmation link in the e-mail lands. The token arrives as a
      // query parameter and the view posts it to the API.
      path: '/verify-email',
      name: 'verify-email',
      component: () => import('@/views/VerifyEmailView.vue'),
      meta: { titleKey: 'titles.verify' },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
      meta: { titleKey: 'titles.notFound' },
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // On a cold load nobody has asked the API who we are yet. Doing it here, once,
  // keeps every guard below working off a settled answer.
  if (!auth.ready) {
    await auth.restore()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    // Remember where they were heading, so signing in resumes it.
    return { name: 'login', query: to.fullPath === '/' ? {} : { next: to.fullPath } }
  }

  // Sent to the dashboard rather than shown a "forbidden" page: somebody
  // following an old bookmark after losing a role has done nothing wrong, and
  // the dashboard is somewhere useful rather than a dead end.
  const required = to.meta.requiresRole

  if (typeof required === 'string' && !auth.hasRole(required as never)) {
    return { name: 'dashboard' }
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  return true
})

router.afterEach((to) => {
  // Titles are translation keys, resolved here rather than stored as text, so
  // the tab title changes with the language like everything else.
  const key = to.meta.titleKey

  document.title =
    typeof key === 'string'
      ? `${i18n.global.t(key as never)} · Fitnessapp`
      : 'Fitnessapp'
})

export default router
