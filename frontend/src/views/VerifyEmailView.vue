<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api, ApiError } from '@/api/client'

/**
 * Where the link in the confirmation mail lands.
 *
 * The token arrives in the query string; this view hands it to the API and
 * reports what happened. The link points here rather than at the API so the
 * user sees a page instead of a JSON document.
 */
const route = useRoute()

const state = ref<'working' | 'confirmed' | 'failed'>('working')
const message = ref('')

onMounted(async () => {
  const token = route.query.token

  if (typeof token !== 'string' || token === '') {
    state.value = 'failed'
    message.value = 'This link is missing its confirmation token.'
    return
  }

  try {
    message.value = (await api.verifyEmail(token)).message
    state.value = 'confirmed'
  } catch (e) {
    state.value = 'failed'
    message.value =
      e instanceof ApiError ? e.message : 'Could not reach the server to confirm your address.'
  }
})
</script>

<template>
  <div class="page narrow">
    <div class="card">
      <template v-if="state === 'working'">
        <h1>Confirming…</h1>
        <p class="muted">One moment.</p>
      </template>

      <template v-else-if="state === 'confirmed'">
        <h1>You're all set</h1>
        <p class="alert alert-success">{{ message }}</p>
        <RouterLink to="/login">Sign in</RouterLink>
      </template>

      <template v-else>
        <h1>That link did not work</h1>
        <p class="alert alert-error">{{ message }}</p>
        <p class="muted small">
          Confirmation links expire after 24 hours and can only be used once. Try
          signing in - if the account is still unconfirmed you can ask for a new link there.
        </p>
        <RouterLink to="/login">Back to sign in</RouterLink>
      </template>
    </div>
  </div>
</template>

<style scoped>
.narrow { max-width: 460px; padding-top: 64px; }
</style>
