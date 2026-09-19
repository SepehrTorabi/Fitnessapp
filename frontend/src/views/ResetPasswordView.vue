<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api, ApiError } from '@/api/client'
import { useApiMessage } from '@/composables/useApiMessage'

/**
 * Where the link in the reset mail lands.
 *
 * The token arrives in the query string and never leaves this view except in
 * the one request that redeems it. Like the confirmation page, the link points
 * here rather than at the API so the user sees a form instead of a JSON
 * document - and unlike it, there is something to fill in, because the mail
 * carries permission to set a password, not the password itself.
 *
 * Redeeming a link does not sign anybody in. That is deliberate on the server
 * and the reason this ends at a link to the sign-in form: the new password gets
 * used once, straight away, while the user still remembers typing it.
 */
const route = useRoute()
const { t } = useI18n()
const apiMessage = useApiMessage()

const password = ref('')
const repeated = ref('')
const error = ref('')
const violations = ref<Record<string, string>>({})
const done = ref(false)
const busy = ref(false)

/**
 * A missing token is worth detecting before the form is filled in: the request
 * would fail whatever was typed, and finding that out after choosing a password
 * is a waste of the user's time.
 */
const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))

async function submit(): Promise<void> {
  error.value = ''
  violations.value = {}

  // Checked here rather than sent to the server: a mismatch is a typo in this
  // form, and the answer does not depend on anything the server knows.
  if (password.value !== repeated.value) {
    error.value = t('auth.passwordsDiffer')
    return
  }

  busy.value = true

  try {
    await api.resetPassword(token.value, password.value)
    done.value = true
  } catch (e) {
    error.value = apiMessage(e, 'auth.resetUnreachable')
    if (e instanceof ApiError) violations.value = e.violations
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="page narrow">
    <div class="card">
      <template v-if="done">
        <h1>{{ t('auth.resetDone') }}</h1>
        <p class="alert alert-success">{{ t('auth.resetDoneIntro') }}</p>
        <RouterLink to="/login">{{ t('auth.signIn') }}</RouterLink>
      </template>

      <template v-else-if="token === ''">
        <h1>{{ t('auth.linkFailed') }}</h1>
        <p class="alert alert-error">{{ t('auth.resetMissingToken') }}</p>
        <p class="muted small">{{ t('auth.resetExpiredHint') }}</p>
        <RouterLink to="/forgot-password">{{ t('auth.askForNewLink') }}</RouterLink>
      </template>

      <template v-else>
        <h1>{{ t('auth.resetTitle') }}</h1>
        <p class="muted small note">{{ t('auth.resetIntro') }}</p>

        <form class="stack" @submit.prevent="submit">
          <div>
            <label for="password">{{ t('auth.newPasswordLabel') }}</label>
            <input
              id="password"
              v-model="password"
              type="password"
              autocomplete="new-password"
              required
            />
            <p class="muted small hint">{{ t('auth.passwordHint') }}</p>
            <p v-if="violations.password" class="field-error">{{ violations.password }}</p>
          </div>

          <div>
            <label for="repeated">{{ t('auth.repeatPasswordLabel') }}</label>
            <input
              id="repeated"
              v-model="repeated"
              type="password"
              autocomplete="new-password"
              required
            />
          </div>

          <p v-if="error" class="alert alert-error">{{ error }}</p>

          <button type="submit" :disabled="busy">
            {{ busy ? t('auth.resetSaving') : t('auth.resetSubmit') }}
          </button>
        </form>

        <!-- The one thing worth offering when the link has expired, which is
             the most likely reason for a failure on this screen. -->
        <p class="muted small footer">
          <RouterLink to="/forgot-password">{{ t('auth.askForNewLink') }}</RouterLink>
        </p>
      </template>
    </div>
  </div>
</template>

<style scoped>
.narrow { max-width: 420px; padding-top: 64px; }
.note { margin: 0 0 16px; }
.hint { margin: 5px 0 0; }
.footer { margin: 20px 0 0; }
</style>
