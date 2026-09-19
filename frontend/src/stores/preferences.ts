import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '@/api/client'
import { applyLocale, directionOf, isSupportedLocale, type SupportedLocale } from '@/i18n'
import { applyTheme, isTheme, resolveSystemTheme, type Theme } from '@/theme'
import {
  cacheCalendar,
  defaultCalendarFor,
  isCalendarSystem,
  readCachedCalendar,
  type CalendarSystem,
} from '@/calendar'
import { useAuthStore } from './auth'

/**
 * Language, colour scheme and calendar.
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
  const calendar = ref<CalendarSystem>('gregorian')
  const error = ref('')

  /**
   * Whether the calendar was actually chosen, rather than inferred from the
   * language.
   *
   * This is what keeps the two settings independent without making the first
   * experience wrong. Picking Farsi before anything else has been decided moves
   * the calendar to Shamsi, because that is overwhelmingly what is wanted and
   * the backend seeds new accounts the same way. Once somebody has picked a
   * calendar themselves, the language stops touching it - which is the case the
   * separation exists for: Farsi text against the Gregorian dates a workplace
   * runs on.
   */
  const calendarChosen = ref(false)

  /** What "system" currently resolves to - what is actually on screen. */
  const effectiveTheme = computed<'light' | 'dark'>(() =>
    theme.value === 'system' ? resolveSystemTheme() : theme.value,
  )

  /**
   * Which way the interface currently runs. Read by the few components whose
   * direction is not just a matter of CSS - the chart has to mirror its own
   * geometry, which no stylesheet can do for it.
   */
  const direction = computed<'rtl' | 'ltr'>(() => directionOf(locale.value))

  /**
   * Read the cached values and put them on screen immediately. Called once
   * before the app mounts, so the first paint is already correct.
   */
  function initFromCache(): void {
    const cachedLocale = read('fitnessapp.locale')
    if (isSupportedLocale(cachedLocale)) locale.value = cachedLocale

    const cachedTheme = read('fitnessapp.theme')
    if (isTheme(cachedTheme)) theme.value = cachedTheme

    const cachedCalendar = readCachedCalendar()

    if (cachedCalendar !== null) {
      calendar.value = cachedCalendar
      calendarChosen.value = true
    } else {
      calendar.value = defaultCalendarFor(locale.value)
    }

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

    if (isCalendarSystem(stored.calendar)) {
      calendar.value = stored.calendar
      // The account holds a settled choice, whatever it came from, so the
      // language must not override it from here on.
      calendarChosen.value = true
      cacheCalendar(stored.calendar)
    }
  }

  async function setLocale(next: SupportedLocale): Promise<void> {
    // Applied first, saved second: the interface should switch the instant it
    // is clicked, not after a round trip.
    locale.value = next
    applyLocale(next)

    // Only while the calendar is still an assumption - see calendarChosen.
    if (!calendarChosen.value) {
      calendar.value = defaultCalendarFor(next)
      await persist({ locale: next, calendar: calendar.value })

      return
    }

    await persist({ locale: next })
  }

  async function setTheme(next: Theme): Promise<void> {
    theme.value = next
    applyTheme(next)
    await persist({ theme: next })
  }

  /**
   * Changing the calendar re-renders every date on screen and nothing else -
   * no date that has been logged moves, because what is stored is always a
   * Gregorian ISO string and only the rendering changes.
   */
  async function setCalendar(next: CalendarSystem): Promise<void> {
    calendar.value = next
    calendarChosen.value = true
    cacheCalendar(next)
    await persist({ calendar: next })
  }

  /**
   * Write through to the account. A failure is worth reporting but must not
   * roll the change back - the user can see it worked, and telling them it did
   * not would be more confusing than the setting being device-local for now.
   */
  async function persist(payload: {
    locale?: SupportedLocale
    theme?: Theme
    calendar?: CalendarSystem
  }): Promise<void> {
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

  return {
    locale,
    theme,
    calendar,
    effectiveTheme,
    direction,
    error,
    initFromCache,
    adoptFromUser,
    setLocale,
    setTheme,
    setCalendar,
  }
})
