<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const { t } = useI18n()

async function signOut(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <header class="nav">
    <div class="nav-inner">
      <RouterLink to="/" class="brand">{{ t('nav.brand') }}</RouterLink>

      <nav class="links">
        <RouterLink to="/">{{ t('nav.dashboard') }}</RouterLink>
        <RouterLink to="/diary">{{ t('nav.diary') }}</RouterLink>
        <RouterLink to="/foods">{{ t('nav.foods') }}</RouterLink>
        <RouterLink to="/profile">{{ t('nav.profile') }}</RouterLink>
        <RouterLink to="/settings">{{ t('nav.settings') }}</RouterLink>
      </nav>

      <div class="account">
        <span class="muted small">{{ auth.user?.displayName }}</span>
        <button class="secondary" type="button" @click="signOut">{{ t('nav.signOut') }}</button>
      </div>
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

.account { display: flex; align-items: center; gap: 10px; }
</style>
