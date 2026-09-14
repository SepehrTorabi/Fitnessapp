/**
 * Colour-scheme handling.
 *
 * Three states, not two. "system" follows the operating system and is the
 * default, because a user who never opens the settings should still get a dark
 * interface at night if that is how their machine is set up. "light" and "dark"
 * are explicit overrides.
 *
 * The state is expressed as a data-theme attribute on <html>, which the CSS in
 * assets/main.css keys off. "system" sets no attribute at all, leaving the
 * prefers-color-scheme media query to decide - the browser then tracks the
 * system setting for us, live, with no JavaScript involved.
 */

export const THEMES = ['system', 'light', 'dark'] as const
export type Theme = (typeof THEMES)[number]

export function isTheme(value: unknown): value is Theme {
  return typeof value === 'string' && (THEMES as readonly string[]).includes(value)
}

/** What the operating system is currently asking for. */
export function resolveSystemTheme(): 'light' | 'dark' {
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

/**
 * Put a theme on screen and remember it for the next cold start.
 */
export function applyTheme(theme: Theme): void {
  const root = document.documentElement

  if (theme === 'system') {
    // No attribute: the media query in the stylesheet takes over, and the
    // browser keeps it in step with the OS without us listening for anything.
    root.removeAttribute('data-theme')
  } else {
    root.setAttribute('data-theme', theme)
  }

  // Tells the browser how to paint things the stylesheet does not own -
  // scrollbars, the default form-control styling, the canvas behind the page.
  root.style.colorScheme = theme === 'system' ? 'light dark' : theme

  try {
    localStorage.setItem('fitnessapp.theme', theme)
  } catch {
    // Private windows and blocked site data. The account holds the real copy.
  }
}

/**
 * Read the cached theme and apply it before Vue mounts.
 *
 * Called from main.ts ahead of createApp, so the very first paint is already
 * the right colour. Doing this inside a component would mean rendering light
 * and then flipping to dark a frame later, which is the flash everyone
 * recognises from badly behaved dark modes.
 */
export function applyCachedThemeEarly(): void {
  let cached: string | null = null

  try {
    cached = localStorage.getItem('fitnessapp.theme')
  } catch {
    cached = null
  }

  applyTheme(isTheme(cached) ? cached : 'system')
}

/**
 * Watch for the operating system flipping between light and dark.
 *
 * The CSS reacts on its own; this exists so components that need to *know* the
 * resolved theme - a chart choosing colours in JavaScript, say - can re-render.
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
