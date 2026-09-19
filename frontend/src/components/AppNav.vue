<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import UserMenu from '@/components/UserMenu.vue'

/**
 * The navigation holds the places the app is actually used, and nothing else.
 * Body data, settings and signing out live under the user's own name in
 * {@see UserMenu} - they are not destinations you move between while logging a
 * meal, and having them here made the bar look like a list of equal choices
 * when it is not.
 *
 * Account administration is not here either, for the same reason and one more:
 * it would be a fourth item that most people can never use.
 */
const { t } = useI18n()
</script>

<template>
  <header class="nav">
    <div class="nav-inner">
      <RouterLink to="/" class="brand">{{ t('nav.brand') }}</RouterLink>

      <nav class="links">
        <RouterLink to="/">{{ t('nav.dashboard') }}</RouterLink>
        <RouterLink to="/diary">{{ t('nav.diary') }}</RouterLink>
        <RouterLink to="/activity">{{ t('nav.activity') }}</RouterLink>
        <RouterLink to="/foods">{{ t('nav.foods') }}</RouterLink>
      </nav>

      <UserMenu />
    </div>
  </header>
</template>

<style scoped>
.nav {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 0;
  z-index: 10;
}

.nav-inner {
  max-width: 1000px;
  margin: 0 auto;
  padding: 10px 16px;
  display: flex;
  align-items: center;
  gap: 20px;
  flex-wrap: wrap;
}

.brand {
  font-weight: 700;
  font-size: 17px;
  color: var(--text);
}

.brand:hover { text-decoration: none; }

.links {
  display: flex;
  gap: 4px;
  flex: 1;
  flex-wrap: wrap;
}

.links a {
  padding: 6px 11px;
  border-radius: var(--radius-sm);
  color: var(--text-muted);
  font-size: 14px;
  font-weight: 500;
}

.links a:hover {
  background: var(--surface-2);
  color: var(--text);
  text-decoration: none;
}

/* vue-router adds router-link-active to the matching link. */
.links a.router-link-exact-active {
  background: var(--surface-2);
  color: var(--accent);
}

</style>
