import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '@/api/client'
import { applyLocale, isSupportedLocale, type SupportedLocale } from '@/i18n'
import { applyTheme, isTheme, resolveSystemTheme, type Theme } from '@/theme'
import { useAuthStore } from './auth'

/**
 * Language and colour scheme.
 *
 * Both live in two places on purpose. The account is the durable copy, so the
 * settings follow the user to another browser or another machine. localStorage
 * is a cache read synchronously at boot, before any network call can answer -
 * without it the app would paint in the wrong theme and then correct itself,
 * which is exactly the flash this avoids.
 *
 * The server always wins when the two disagree.
 */
export const usePreferencesStore = defineStore('preferences', () => {
  const locale = ref<SupportedLocale>('en')
  const theme = ref<Theme>('system')
  const error = ref('')

  /** What "system" currently resolves to - what is actually on screen. */
  const effectiveTheme = computed<'light' | 'dark'>(() =>
    theme.value === 'system' ? resolveSystemTheme() : theme.value,
  )

  /**
   * Read the cached values and put them on screen immediately. Called once
   * before the app mounts, so the first paint is already correct.
   */
  function initFromCache(): void {
    const cachedLocale = read('fitnessapp.locale')
    if (isSupportedLocale(cachedLocale)) locale.value = cachedLocale

    const cachedTheme = read('fitnessapp.theme')
    if (isTheme(cachedTheme)) theme.value = cachedTheme

    applyLocale(locale.value)
    applyTheme(theme.value)
  }

  /**
   * Adopt whatever the account says. The API always sends both fields, so a
   * signed-in user's stored choice overrides the local cache.
   */
  function adoptFromUser(): void {
    const stored = useAuthStore().user?.preferences
    if (!stored) return

    if (isSupportedLocale(stored.locale)) {
      locale.value = stored.locale
      applyLocale(stored.locale)
    }

    if (isTheme(stored.theme)) {
      theme.value = stored.theme
      applyTheme(stored.theme)
    }
  }

  async function setLocale(next: SupportedLocale): Promise<void> {
    // Applied first, saved second: the interface should switch the instant it
    // is clicked, not after a round trip.
    locale.value = next
    applyLocale(next)
    await persist({ locale: next })
  }

  async function setTheme(next: Theme): Promise<void> {
    theme.value = next
    applyTheme(next)
    await persist({ theme: next })
  }

  /**
   * Write through to the account. A failure is worth reporting but must not
   * roll the change back - the user can see it worked, and telling them it did
   * not would be more confusing than the setting being device-local for now.
   */
  async function persist(payload: { locale?: SupportedLocale; theme?: Theme }): Promise<void> {
    error.value = ''

    if (!useAuthStore().isAuthenticated) return

    try {
      useAuthStore().setUser((await api.updatePreferences(payload)).user)
    } catch {
      error.value = 'settings.offlineNote'
    }
  }

  function read(key: string): string | null {
    try {
      return localStorage.getItem(key)
    } catch {
      return null
    }
  }

  return { locale, theme, effectiveTheme, error, initFromCache, adoptFromUser, setLocale, setTheme }
})
