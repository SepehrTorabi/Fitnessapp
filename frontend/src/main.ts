import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import { i18n } from './i18n'
import { applyCachedThemeEarly } from './theme'
import './assets/main.css'

// Before anything renders. Reading the cached theme here rather than inside a
// component is what stops the app painting light and then flipping to dark a
// frame later.
applyCachedThemeEarly()

createApp(App).use(createPinia()).use(router).use(i18n).mount('#app')
