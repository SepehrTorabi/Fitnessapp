import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vite.dev/config/
export default defineConfig({
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
})
