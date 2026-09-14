import { createI18n } from 'vue-i18n'
import en from './locales/en'
import de from './locales/de'

/**
 * Translation setup.
 *
 * English is the reference catalogue and every other language is typed against
 * it: `MessageSchema` is derived from en.ts, so a key that exists in English
 * but is missing from German is a compile error rather than an English string
 * showing up in a German interface.
 */
export type MessageSchema = typeof en

export const SUPPORTED_LOCALES = ['en', 'de'] as const
export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number]

/** How each language names itself - a language picker has to be readable by
 *  someone who cannot read the language currently in use. */
export const LOCALE_ENDONYMS: Record<SupportedLocale, string> = {
  en: 'English',
  de: 'Deutsch',
}

export function isSupportedLocale(value: unknown): value is SupportedLocale {
  return typeof value === 'string' && (SUPPORTED_LOCALES as readonly string[]).includes(value)
}

/**
 * The language to start in, before the API has said what the user chose.
 *
 * Only a first guess: once /api/me answers, the stored preference wins. Picking
 * something sensible here is what stops the login screen flashing English at a
 * German user for half a second.
 */
export function detectInitialLocale(): SupportedLocale {
  const stored = safeRead('fitnessapp.locale')
  if (isSupportedLocale(stored)) return stored

  for (const tag of navigator.languages ?? [navigator.language]) {
    const primary = tag.split('-')[0]?.toLowerCase()
    if (isSupportedLocale(primary)) return primary
  }

  return 'en'
}

function safeRead(key: string): string | null {
  // localStorage throws in a private window with site data blocked, and the
  // app has to keep working when it does.
  try {
    return localStorage.getItem(key)
  } catch {
    return null
  }
}

// The third type argument is the `legacy` flag. Without it TypeScript cannot
// tell which of the two APIs this instance exposes, and `global.locale` comes
// back typed as a plain string instead of the writable ref it actually is in
// Composition mode.
export const i18n = createI18n<[MessageSchema], SupportedLocale, false>({
  // The Composition API mode. `legacy: false` is what makes useI18n() work.
  legacy: false,
  locale: detectInitialLocale(),
  fallbackLocale: 'en',
  messages: { en, de },
  // Missing keys already fail at compile time, so warning about them again at
  // runtime only adds noise to the console.
  missingWarn: false,
  fallbackWarn: false,
})

/**
 * Change the language everywhere at once: vue-i18n, the <html lang> attribute
 * that screen readers and browser translation rely on, and the copy in
 * localStorage that seeds the next cold start.
 */
export function applyLocale(locale: SupportedLocale): void {
  i18n.global.locale.value = locale
  document.documentElement.setAttribute('lang', locale)

  try {
    localStorage.setItem('fitnessapp.locale', locale)
  } catch {
    // A per-device convenience. The real copy lives on the account.
  }
}
