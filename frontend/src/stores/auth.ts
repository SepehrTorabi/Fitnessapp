import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, ApiError } from '@/api/client'
import type { User } from '@/api/types'

/**
 * Who is signed in.
 *
 * With a session cookie the frontend holds no token of its own, so "am I signed
 * in?" is not something it can answer on its own - it has to ask the API. That
 * is what `restore()` does on boot, and why `ready` exists: the router must wait
 * for the answer before deciding where to send the user, or a reload on any page
 * would bounce through the login screen.
 */
export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const ready = ref(false)

  const isAuthenticated = computed(() => user.value !== null)

  /** The dashboard needs body data before it can show anything meaningful. */
  const needsProfile = computed(() => user.value !== null && user.value.profile === null)

  const needsWeight = computed(
    () => user.value !== null && user.value.latestMeasurement === null,
  )

  const dailyTarget = computed(() => user.value?.dailyTarget ?? null)

  /**
   * Ask the API who we are. A 401 is the expected answer for a visitor, not an
   * error worth surfacing.
   */
  async function restore(): Promise<void> {
    try {
      user.value = (await api.me()).user
    } catch (error) {
      if (error instanceof ApiError && error.isUnauthenticated) {
        user.value = null
      } else {
        throw error
      }
    } finally {
      ready.value = true
    }
  }

  async function login(email: string, password: string): Promise<void> {
    user.value = (await api.login(email, password)).user
  }

  async function logout(): Promise<void> {
    try {
      await api.logout()
    } finally {
      // Clear locally whatever the server said: a failed logout must not leave
      // the UI claiming the user is still signed in.
      user.value = null
    }
  }

  /**
   * Refresh the cached user after something changed the profile, a measurement
   * or - through either of those - the calorie target.
   */
  async function refresh(): Promise<void> {
    if (user.value === null) return

    user.value = (await api.me()).user
  }

  function setUser(next: User | null): void {
    user.value = next
  }

  return {
    user,
    ready,
    isAuthenticated,
    needsProfile,
    needsWeight,
    dailyTarget,
    restore,
    login,
    logout,
    refresh,
    setUser,
  }
})
