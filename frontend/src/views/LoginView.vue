<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const email = ref('')
const password = ref('')
const error = ref('')
const notice = ref('')
const busy = ref(false)

/**
 * Shown only after a login fails because the address is unconfirmed - that is
 * the one moment when offering to resend the link is useful rather than noise.
 */
const showResend = ref(false)

async function submit(): Promise<void> {
  error.value = ''
  notice.value = ''
  showResend.value = false
  busy.value = true

  try {
    await auth.login(email.value, password.value)

    // Resume wherever the guard interrupted them, or start at the dashboard.
    const next = route.query.next
    await router.push(typeof next === 'string' ? next : { name: 'dashboard' })
  } catch (e) {
    if (e instanceof ApiError) {
      error.value = e.message
      showResend.value = e.message.toLowerCase().includes('confirm')
    } else {
      error.value = 'Could not reach the server. Is the API running?'
    }
  } finally {
    busy.value = false
  }
}

async function resend(): Promise<void> {
  busy.value = true
  error.value = ''

  try {
    notice.value = (await api.resendVerification(email.value)).message
    showResend.value = false
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Could not send the mail.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="page narrow">
    <div class="card">
      <h1>Sign in</h1>

      <form class="stack" @submit.prevent="submit">
        <div>
          <label for="email">E-mail address</label>
          <input id="email" v-model="email" type="email" autocomplete="email" required />
        </div>

        <div>
          <label for="password">Password</label>
          <input
            id="password"
            v-model="password"
            type="password"
            autocomplete="current-password"
            required
          />
        </div>

        <p v-if="error" class="alert alert-error">{{ error }}</p>
        <p v-if="notice" class="alert alert-success">{{ notice }}</p>

        <button type="submit" :disabled="busy">{{ busy ? 'Signing in…' : 'Sign in' }}</button>

        <button v-if="showResend" class="secondary" type="button" :disabled="busy" @click="resend">
          Send the confirmation link again
        </button>
      </form>

      <p class="muted small footer">
        No account yet? <RouterLink to="/register">Create one</RouterLink>
      </p>
    </div>
  </div>
</template>

<style scoped>
.narrow { max-width: 420px; padding-top: 64px; }
.footer { margin: 20px 0 0; }
</style>
