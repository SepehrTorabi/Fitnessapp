import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig(({ command }) => ({
  /*
   * In development Vite serves the app at the root of its own dev server. A
   * production build is served from /app/ inside the Symfony public directory -
   * one web server for both halves, so the session cookie stays same-origin -
   * and the built asset URLs have to match that path or every script 404s.
   *
   * Vite exposes this as import.meta.env.BASE_URL, which is what the router
   * uses for its own base, so the two can never drift apart.
   */
  base: command === 'build' ? '/app/' : '/',

  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: 5173,
    proxy: {
      // Everything under /api is forwarded to the Symfony dev server, so from
      // the browser's point of view the SPA and the API share an origin. That
      // matters for the session cookie: same-origin means no CORS preflight and
      // no cross-site cookie rules to fight. `symfony server:start` must be
      // running on 8000 alongside `npm run dev`.
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: false,
      },
    },
  },
}))
