import { createI18n } from 'vue-i18n'
import en from './locales/en'
import de from './locales/de'
import fa from './locales/fa'

/**
 * Translation setup.
 *
 * English is the reference catalogue and every other language is typed against
 * it: `MessageSchema` is derived from en.ts, so a key that exists in English
 * but is missing from German is a compile error rather than an English string
 * showing up in a German interface.
 */
export type MessageSchema = typeof en

export const SUPPORTED_LOCALES = ['en', 'de', 'fa'] as const
export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number]

/** How each language names itself - a language picker has to be readable by
 *  someone who cannot read the language currently in use. */
export const LOCALE_ENDONYMS: Record<SupportedLocale, string> = {
  en: 'English',
  de: 'Deutsch',
  fa: 'فارسی',
}

/**
 * Which languages are written right to left.
 *
 * A property of the language, so it is declared once here rather than checked
 * with `locale === 'fa'` in every component that cares. Adding Arabic or Hebrew
 * later means adding a line here and nothing else.
 */
export const RTL_LOCALES: readonly SupportedLocale[] = ['fa']

export function isRtl(locale: SupportedLocale): boolean {
  return RTL_LOCALES.includes(locale)
}

export function directionOf(locale: SupportedLocale): 'rtl' | 'ltr' {
  return isRtl(locale) ? 'rtl' : 'ltr'
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
  messages: { en, de, fa },
  // Missing keys already fail at compile time, so warning about them again at
  // runtime only adds noise to the console.
  missingWarn: false,
  fallbackWarn: false,
})

/**
 * Change the language everywhere at once: vue-i18n, the <html lang> attribute
 * that screen readers and browser translation rely on, the writing direction,
 * and the copy in localStorage that seeds the next cold start.
 *
 * The direction goes on <html dir> rather than being handled in CSS, and that
 * is the whole reason the right-to-left work is as small as it is. One
 * attribute flips the block direction of the entire document, and every rule
 * written with logical properties - margin-inline-start rather than
 * margin-left, text-align: start rather than left - follows it without being
 * told. What is left over is the handful of places where direction is not a
 * property of a box at all: the chart's own geometry, and the runs of text that
 * must stay left-to-right whatever surrounds them, like a URL or a barcode.
 */
export function applyLocale(locale: SupportedLocale): void {
  i18n.global.locale.value = locale
  document.documentElement.setAttribute('lang', locale)
  document.documentElement.setAttribute('dir', directionOf(locale))

  try {
    localStorage.setItem('fitnessapp.locale', locale)
  } catch {
    // A per-device convenience. The real copy lives on the account.
  }
}

/**
 * Set the direction before Vue mounts, from the cached language.
 *
 * Called from main.ts alongside the cached theme: without it a Farsi user sees
 * the first frame laid out left to right and then watches it flip, which is a
 * good deal more jarring than a flash of the wrong colour.
 */
export function applyCachedDirectionEarly(): void {
  const locale = detectInitialLocale()

  document.documentElement.setAttribute('lang', locale)
  document.documentElement.setAttribute('dir', directionOf(locale))
}
