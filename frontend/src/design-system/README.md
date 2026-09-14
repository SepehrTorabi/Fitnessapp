# Design system (vendored)

These files are **copied, unmodified**, from a separate repository. Do not edit
them here — a change made in this folder is lost the next time they are refreshed,
and it silently forks the design system.

| | |
|---|---|
| Source | https://github.com/costenrechnung/Design-System |
| Commit | `0c03a7831f60964a7f764db9bf3ba5fbc014fe5c` ("Make the app deployable to GitHub Pages") |
| Copied on | 2026-09-14 |

## What is here, and why it is only this much

The design system is **React 19 + TypeScript**; this application is **Vue 3**.
The `.tsx` components cannot be used directly. Their `.module.css` files can:
they contain no React, only token-driven CSS with semantic class names.

So the split is:

- `tokens/` — `tokens.css` and `tokens/core.css`, byte-for-byte from the source.
  These are the whole visual language: colour, type, spacing, radii, motion.
- `css/` — the `.module.css` file of each component this app uses, also
  byte-for-byte. These are the real styles; nothing is re-implemented.
- `components/` — thin Vue wrappers that render the same DOM the React
  component renders and attach the same classes from the imported CSS module.
  This is the only code here that is ours, and it is deliberately as small as
  possible.

The result is that the *styling* comes from the design system rather than
resembling it. Only the framework binding is local.

## Refreshing

Re-copy `tokens.css`, `tokens/core.css` and the used `.module.css` files from the
source repository, update the commit above, then run `npm run build` and look at
the pages. If a component's markup changed upstream, its wrapper in
`components/` needs the same change — the wrappers mirror markup, so they are
the thing that goes stale.

## Two adaptations, made outside these files

Neither of these edits the vendored files; both are handled in the app.

1. **`data-brand` and `data-theme` are on different elements.** `core.css` scopes
   its dark palette with a descendant selector, `[data-brand="core"] [data-theme="dark"]`,
   because in the source project the theme lives on a nested preview frame. Putting
   both attributes on `<html>` would make that selector never match. The app
   therefore sets `data-brand="core"` on `<html>` and `data-theme` on `<body>`.

2. **The theme is always resolved to light or dark.** The design system has two
   palettes, no "follow the system" state. The app's third setting is resolved in
   JavaScript before painting and kept in step with the OS by a media-query
   listener — see `src/theme.ts`.
