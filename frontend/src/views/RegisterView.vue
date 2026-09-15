<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api, ApiError } from '@/api/client'
import { useApiMessage } from '@/composables/useApiMessage'
import { usePreferencesStore } from '@/stores/preferences'

const { t } = useI18n()
const apiMessage = useApiMessage()
const preferences = usePreferencesStore()

const displayName = ref('')
const email = ref('')
const password = ref('')

const error = ref('')
const violations = ref<Record<string, string>>({})
const busy = ref(false)
const done = ref(false)

async function submit(): Promise<void> {
  error.value = ''
  violations.value = {}
  busy.value = true

  try {
    await api.register({
      email: email.value,
      password: password.value,
      displayName: displayName.value,
      // The account does not exist yet, so there is no stored preference - but
      // the confirmation mail goes out immediately. Sending what this form was
      // displayed in means the first thing the user reads from us is already
      // in their language.
      locale: preferences.locale,
      // These two do not affect the mail. They travel so that the first screen
      // after signing in looks like the form the user just filled in, rather
      // than resetting to light and Gregorian and making them choose twice.
      theme: preferences.theme,
      calendar: preferences.calendar,
    })
    done.value = true
  } catch (e) {
    error.value = apiMessage(e, 'auth.unreachable')

    // Field-level messages from the API's validator, keyed by property name.
    if (e instanceof ApiError) violations.value = e.violations
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="page narrow">
    <!-- The account exists but cannot sign in yet, so the only useful next step
         is the inbox. -->
    <div v-if="done" class="card">
      <h1>{{ t('auth.checkInbox') }}</h1>
      <i18n-t keypath="auth.confirmationSent" tag="p" scope="global">
        <template #email><strong>{{ email }}</strong></template>
      </i18n-t>
      <i18n-t keypath="auth.mailpitHint" tag="p" class="muted small" scope="global">
        <template #link>
          <a href="http://localhost:8025" target="_blank" rel="noopener">localhost:8025</a>
        </template>
      </i18n-t>
      <RouterLink to="/login">{{ t('auth.backToSignIn') }}</RouterLink>
    </div>

    <div v-else class="card">
      <h1>{{ t('auth.createAccount') }}</h1>

      <form class="stack" @submit.prevent="submit">
        <div>
          <label for="displayName">{{ t('auth.nameLabel') }}</label>
          <input
            id="displayName"
            v-model="displayName"
            type="text"
            autocomplete="name"
            required
            :aria-invalid="Boolean(violations.displayName)"
          />
          <p v-if="violations.displayName" class="field-error">{{ violations.displayName }}</p>
        </div>

        <div>
          <label for="email">{{ t('auth.emailLabel') }}</label>
          <input
            id="email"
            v-model="email"
            type="email"
            autocomplete="email"
            required
            :aria-invalid="Boolean(violations.email)"
          />
          <p v-if="violations.email" class="field-error">{{ violations.email }}</p>
        </div>

        <div>
          <label for="password">{{ t('auth.passwordLabel') }}</label>
          <input
            id="password"
            v-model="password"
            type="password"
            autocomplete="new-password"
            required
            :aria-invalid="Boolean(violations.password)"
          />
          <p v-if="violations.password" class="field-error">{{ violations.password }}</p>
          <p v-else class="muted small hint">{{ t('auth.passwordHint') }}</p>
        </div>

        <p v-if="error" class="alert alert-error">{{ error }}</p>

        <button type="submit" :disabled="busy">
          {{ busy ? t('auth.creating') : t('auth.createAccount') }}
        </button>
      </form>

      <p class="muted small footer">
        {{ t('auth.haveAccount') }} <RouterLink to="/login">{{ t('auth.signIn') }}</RouterLink>
      </p>
    </div>
  </div>
</template>

<style scoped>
.narrow { max-width: 420px; padding-top: 64px; }
.footer { margin: 20px 0 0; }
.hint { margin: 4px 0 0; }
</style>
