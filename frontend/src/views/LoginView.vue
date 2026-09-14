<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api, ApiError } from '@/api/client'
import { useApiMessage } from '@/composables/useApiMessage'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const { t } = useI18n()
const apiMessage = useApiMessage()

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
    error.value = apiMessage(e, 'auth.unreachable')

    // The API keeps the unconfirmed-account case distinguishable from a wrong
    // password, so the offer to resend appears only when it would help.
    showResend.value =
      e instanceof ApiError && e.status === 401 && e.message.toLowerCase().includes('confirm')
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
    error.value = apiMessage(e, 'auth.sendFailed')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="page narrow">
    <div class="card">
      <h1>{{ t('auth.signIn') }}</h1>

      <form class="stack" @submit.prevent="submit">
        <div>
          <label for="email">{{ t('auth.emailLabel') }}</label>
          <input id="email" v-model="email" type="email" autocomplete="email" required />
        </div>

        <div>
          <label for="password">{{ t('auth.passwordLabel') }}</label>
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

        <button type="submit" :disabled="busy">
          {{ busy ? t('auth.signingIn') : t('auth.signIn') }}
        </button>

        <button v-if="showResend" class="secondary" type="button" :disabled="busy" @click="resend">
          {{ t('auth.resendLink') }}
        </button>
      </form>

      <p class="muted small footer">
        {{ t('auth.noAccount') }} <RouterLink to="/register">{{ t('auth.createOne') }}</RouterLink>
      </p>
    </div>
  </div>
</template>

<style scoped>
.narrow { max-width: 420px; padding-top: 64px; }
.footer { margin: 20px 0 0; }
</style>
