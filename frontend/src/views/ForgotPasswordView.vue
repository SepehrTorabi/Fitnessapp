<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api } from '@/api/client'
import { useApiMessage } from '@/composables/useApiMessage'

/**
 * Asking for a reset link.
 *
 * The API answers the same way whether or not the address has an account, so
 * this screen has nothing to branch on: it shows what came back and stops
 * offering the form. Repeating the message the server sent, rather than one of
 * our own, is what keeps the two cases indistinguishable here as well - a
 * different layout for "sent" and "no such account" would leak exactly what
 * the endpoint is careful not to.
 */
const { t } = useI18n()
const apiMessage = useApiMessage()

const email = ref('')
const notice = ref('')
const error = ref('')
const busy = ref(false)

async function submit(): Promise<void> {
  error.value = ''
  notice.value = ''
  busy.value = true

  try {
    notice.value = (await api.forgotPassword(email.value)).message
  } catch (e) {
    error.value = apiMessage(e, 'auth.forgotUnreachable')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="page narrow">
    <div class="card">
      <h1>{{ t('auth.forgotTitle') }}</h1>

      <!-- Once the link is on its way there is nothing useful left to do here,
           so the form is replaced rather than left sitting under the message
           inviting a second identical request. -->
      <template v-if="notice">
        <p class="alert alert-success">{{ notice }}</p>
        <p class="muted small footer">
          <RouterLink to="/login">{{ t('auth.backToSignIn') }}</RouterLink>
        </p>
      </template>

      <template v-else>
        <p class="muted small note">{{ t('auth.forgotIntro') }}</p>

        <form class="stack" @submit.prevent="submit">
          <div>
            <label for="email">{{ t('auth.emailLabel') }}</label>
            <input id="email" v-model="email" type="email" autocomplete="email" required />
          </div>

          <p v-if="error" class="alert alert-error">{{ error }}</p>

          <button type="submit" :disabled="busy">
            {{ busy ? t('auth.forgotSending') : t('auth.forgotSubmit') }}
          </button>
        </form>

        <p class="muted small footer">
          <RouterLink to="/login">{{ t('auth.backToSignIn') }}</RouterLink>
        </p>
      </template>
    </div>
  </div>
</template>

<style scoped>
.narrow { max-width: 420px; padding-top: 64px; }
.note { margin: 0 0 16px; }
.footer { margin: 20px 0 0; }
</style>
