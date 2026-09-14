<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api } from '@/api/client'
import { useApiMessage } from '@/composables/useApiMessage'

/**
 * Where the link in the confirmation mail lands.
 *
 * The token arrives in the query string; this view hands it to the API and
 * reports what happened. The link points here rather than at the API so the
 * user sees a page instead of a JSON document.
 */
const route = useRoute()
const { t } = useI18n()
const apiMessage = useApiMessage()

const state = ref<'working' | 'confirmed' | 'failed'>('working')
const message = ref('')

onMounted(async () => {
  const token = route.query.token

  if (typeof token !== 'string' || token === '') {
    state.value = 'failed'
    message.value = t('auth.linkMissingToken')
    return
  }

  try {
    message.value = (await api.verifyEmail(token)).message
    state.value = 'confirmed'
  } catch (e) {
    state.value = 'failed'
    message.value = apiMessage(e, 'auth.verifyUnreachable')
  }
})
</script>

<template>
  <div class="page narrow">
    <div class="card">
      <template v-if="state === 'working'">
        <h1>{{ t('auth.confirming') }}</h1>
        <p class="muted">{{ t('auth.oneMoment') }}</p>
      </template>

      <template v-else-if="state === 'confirmed'">
        <h1>{{ t('auth.allSet') }}</h1>
        <p class="alert alert-success">{{ message }}</p>
        <RouterLink to="/login">{{ t('auth.signIn') }}</RouterLink>
      </template>

      <template v-else>
        <h1>{{ t('auth.linkFailed') }}</h1>
        <p class="alert alert-error">{{ message }}</p>
        <p class="muted small">{{ t('auth.linkExpiredHint') }}</p>
        <RouterLink to="/login">{{ t('auth.backToSignIn') }}</RouterLink>
      </template>
    </div>
  </div>
</template>

<style scoped>
.narrow { max-width: 460px; padding-top: 64px; }
</style>
