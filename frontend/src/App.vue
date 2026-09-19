<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { RouterView } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { usePreferencesStore } from '@/stores/preferences'
import AppNav from '@/components/AppNav.vue'
import AppearanceControls from '@/components/AppearanceControls.vue'

const auth = useAuthStore()
const preferences = usePreferencesStore()
const { t } = useI18n()

// The cached language and theme, on screen before the first network call.
preferences.initFromCache()

// Once the API says who is signed in, their stored settings win over the cache
// - that is what makes them follow the user to another browser.
watch(() => auth.user, () => preferences.adoptFromUser(), { immediate: true })

onMounted(() => preferences.adoptFromUser())
</script>

<template>
  <!-- The router guard resolves who is signed in before the first view renders,
       so there is nothing meaningful to show until it has answered. -->
  <div v-if="!auth.ready" class="boot">{{ t('common.loading') }}</div>

  <template v-else>
    <AppNav v-if="auth.isAuthenticated" />

    <!-- Before there is an account there is still a person, and they may not
         read English. The language has to be reachable on the sign-in screen
         itself - it decides what the confirmation mail is written in, which is
         sent before the user can reach any settings page at all. Nothing here
         touches the database: the store skips the network call while signed
         out, and the sign-up form sends the choices along so they survive into
         the new account. -->
    <div v-else class="guest-bar">
      <AppearanceControls />
    </div>

    <RouterView />
  </template>
</template>

<style scoped>
.guest-bar {
  display: flex;
  justify-content: flex-end;
  gap: var(--spacing-small);
  max-width: 1040px;
  margin: 0 auto;
  padding: var(--spacing-small) var(--spacing-medium) 0;
}

.boot {
  display: grid;
  place-items: center;
  min-height: 60vh;
  color: var(--text-muted);
}
</style>
