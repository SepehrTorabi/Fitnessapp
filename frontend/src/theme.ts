/**
 * Colour-scheme handling, on top of the design system's tokens.
 *
 * Three settings, two palettes. "system" is a preference, not a palette: the
 * design system defines light and dark and nothing in between, so "system" is
 * resolved to one of them before anything is painted, and a media-query
 * listener keeps it in step while the user stays on that setting.
 *
 * The two attributes live on different elements on purpose. `core.css` scopes
 * its dark palette with a descendant selector - `[data-brand="core"] [data-theme="dark"]` -
 * because in the design system's own project the theme sits on a nested preview
 * frame. Putting both on <html> would make that selector match nothing and the
 * brand's dark colours would silently never apply. So the brand goes on <html>
 * and the theme on <body>.
 */

export const THEMES = ['system', 'light', 'dark'] as const
export type Theme = (typeof THEMES)[number]

/** The design system brand this application uses. */
const BRAND = 'core'

const STORAGE_KEY = 'fitnessapp.theme'

export function isTheme(value: unknown): value is Theme {
  return typeof value === 'string' && (THEMES as readonly string[]).includes(value)
}

/** What the operating system is currently asking for. */
export function resolveSystemTheme(): 'light' | 'dark' {
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

/**
 * Removed and re-registered by applyTheme, so the OS listener is active only
 * while the user is actually on "system".
 */
let stopWatchingSystem: (() => void) | null = null

/**
 * Put a theme on screen and remember it for the next cold start.
 */
export function applyTheme(theme: Theme): void {
  document.documentElement.setAttribute('data-brand', BRAND)

  paint(theme === 'system' ? resolveSystemTheme() : theme)

  stopWatchingSystem?.()
  stopWatchingSystem = null

  // Only "system" has anything to follow. On an explicit choice the listener is
  // dropped, so the OS flipping at sunset cannot override what the user picked.
  if (theme === 'system') {
    stopWatchingSystem = onSystemThemeChange(paint)
  }

  try {
    localStorage.setItem(STORAGE_KEY, theme)
  } catch {
    // Private windows and blocked site data. The account holds the real copy.
  }
}

function paint(resolved: 'light' | 'dark'): void {
  // <body> is the element the design system's dark rules are written against.
  // It exists by the time this runs - the entry module is loaded at the end of
  // the body - but guard anyway rather than failing silently in another host.
  const target = document.body ?? document.documentElement

  target.setAttribute('data-theme', resolved)

  // Paints what the stylesheet does not own: scrollbars, default form controls,
  // the canvas behind the page.
  document.documentElement.style.colorScheme = resolved
}

/**
 * Read the cached theme and apply it before Vue mounts.
 *
 * Called from main.ts ahead of createApp, so the very first paint is already the
 * right colour rather than light-then-dark a frame later.
 */
export function applyCachedThemeEarly(): void {
  let cached: string | null = null

  try {
    cached = localStorage.getItem(STORAGE_KEY)
  } catch {
    cached = null
  }

  applyTheme(isTheme(cached) ? cached : 'system')
}

/**
 * Watch for the operating system flipping between light and dark.
 *
 * @returns a function that removes the listener
 */
export function onSystemThemeChange(handler: (theme: 'light' | 'dark') => void): () => void {
  const query = window.matchMedia?.('(prefers-color-scheme: dark)')

  if (!query) return () => {}

  const listener = (event: MediaQueryListEvent): void => {
    handler(event.matches ? 'dark' : 'light')
  }

  query.addEventListener('change', listener)

  return () => query.removeEventListener('change', listener)
}
