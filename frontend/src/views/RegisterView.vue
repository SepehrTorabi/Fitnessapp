<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'

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
    })
    done.value = true
  } catch (e) {
    if (e instanceof ApiError) {
      error.value = e.message
      // Field-level messages from the API's validator, keyed by property name.
      violations.value = e.violations
    } else {
      error.value = 'Could not reach the server. Is the API running?'
    }
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
      <h1>Check your inbox</h1>
      <p>
        We sent a confirmation link to <strong>{{ email }}</strong>. Open it to
        activate your account, then sign in.
      </p>
      <p class="muted small">
        Running locally? The mail is waiting in Mailpit at
        <a href="http://localhost:8025" target="_blank" rel="noopener">localhost:8025</a>.
      </p>
      <RouterLink to="/login">Back to sign in</RouterLink>
    </div>

    <div v-else class="card">
      <h1>Create an account</h1>

      <form class="stack" @submit.prevent="submit">
        <div>
          <label for="displayName">Your name</label>
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
          <label for="email">E-mail address</label>
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
          <label for="password">Password</label>
          <input
            id="password"
            v-model="password"
            type="password"
            autocomplete="new-password"
            required
            :aria-invalid="Boolean(violations.password)"
          />
          <p v-if="violations.password" class="field-error">{{ violations.password }}</p>
          <p v-else class="muted small hint">At least 10 characters.</p>
        </div>

        <p v-if="error" class="alert alert-error">{{ error }}</p>

        <button type="submit" :disabled="busy">
          {{ busy ? 'Creating…' : 'Create account' }}
        </button>
      </form>

      <p class="muted small footer">
        Already have an account? <RouterLink to="/login">Sign in</RouterLink>
      </p>
    </div>
  </div>
</template>

<style scoped>
.narrow { max-width: 420px; padding-top: 64px; }
.footer { margin: 20px 0 0; }
.hint { margin: 4px 0 0; }
</style>
