import { useI18n } from 'vue-i18n'
import { ApiError } from '@/api/client'

/**
 * Turns whatever went wrong into a sentence in the user's language.
 *
 * The API answers failures with both a stable `error` code and an English
 * `message`. Translating the code is what makes a German user see a German
 * error; the server's own wording is the fallback for a code this catalogue
 * does not know yet, which is better than showing nothing at all.
 *
 * Anything that is not an ApiError never reached the server - a dead network, a
 * dev server that is not running - so it gets the caller's own fallback key.
 */
export function useApiMessage(): (error: unknown, fallbackKey: string) => string {
  const { t, te } = useI18n()

  return (error: unknown, fallbackKey: string): string => {
    if (error instanceof ApiError) {
      const key = `errors.${error.code}`

      return te(key) ? t(key) : error.message
    }

    return t(fallbackKey)
  }
}
