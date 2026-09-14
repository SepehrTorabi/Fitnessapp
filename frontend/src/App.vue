<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { RouterView } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { usePreferencesStore } from '@/stores/preferences'
import AppNav from '@/components/AppNav.vue'

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
    <RouterView />
  </template>
</template>

<style scoped>
.boot {
  display: grid;
  place-items: center;
  min-height: 60vh;
  color: var(--text-muted);
}
</style>
