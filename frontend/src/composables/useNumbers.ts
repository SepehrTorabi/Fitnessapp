import { useI18n } from 'vue-i18n'

/**
 * Numbers written the way the current language writes them.
 *
 * Farsi is what forced this. Persian uses its own digits - ۰۱۲۳۴۵۶۷۸۹ - and
 * Intl already renders every date in them, so a page that formats its dates
 * through Intl and its calories through `Math.round()` ends up with "۲۴ شهریور"
 * one line above "3141 kcal". Neither is wrong on its own; together they look
 * like two applications stitched together.
 *
 * It earns its keep in the other languages too: German writes 1.234,5 where
 * English writes 1,234.5, and both were coming out in whatever JavaScript's
 * default happened to be.
 *
 * The formatters are built once per language rather than per call.
 * Intl.NumberFormat is expensive to construct and these run inside render
 * functions, several times per row of a table.
 */
export function useNumbers() {
  const { locale } = useI18n()

  const cache = new Map<string, Intl.NumberFormat>()

  function formatter(decimals: number): Intl.NumberFormat {
    const key = `${locale.value}:${decimals}`
    let found = cache.get(key)

    if (!found) {
      found = new Intl.NumberFormat(locale.value, {
        minimumFractionDigits: 0,
        maximumFractionDigits: decimals,
      })
      cache.set(key, found)
    }

    return found
  }

  /** A whole number: calories, grams, minutes. */
  function n(value: number | null | undefined): string {
    if (value === null || value === undefined || Number.isNaN(value)) return '—'

    return formatter(0).format(Math.round(value))
  }

  /** A number that may carry a decimal, such as an amount the user typed. */
  function decimal(value: number | null | undefined, places = 1): string {
    if (value === null || value === undefined || Number.isNaN(value)) return '—'

    return formatter(places).format(value)
  }

  return { n, decimal }
}
