<script setup lang="ts">
import { reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api, ApiError } from '@/api/client'
import { useApiMessage } from '@/composables/useApiMessage'
import { usePreferencesStore } from '@/stores/preferences'
import { LOCALE_ENDONYMS, SUPPORTED_LOCALES, type SupportedLocale } from '@/i18n'
import { THEMES, type Theme } from '@/theme'
import { CALENDARS, formatDate, todayIso, type CalendarSystem } from '@/calendar'

/**
 * Language, appearance, calendar and the password.
 *
 * The first three apply the moment they are clicked and are written to the
 * account in the background - there is no Save button, because there is nothing
 * to review. A setting whose effect you can see immediately does not need
 * confirming.
 *
 * The password is the exception and has a form with a button, because it is the
 * one thing on this page you cannot see the effect of, cannot undo by clicking
 * the other option, and would not want to trigger by a stray keystroke.
 */
const { t, locale } = useI18n()
const preferences = usePreferencesStore()
const apiMessage = useApiMessage()

const themeIcons: Record<Theme, string> = {
  system: '🖥',
  light: '☀',
  dark: '🌙',
}

function themeLabel(theme: Theme): string {
  return t(`settings.theme${theme.charAt(0).toUpperCase()}${theme.slice(1)}` as never)
}

async function chooseLocale(locale: SupportedLocale): Promise<void> {
  await preferences.setLocale(locale)
}

async function chooseTheme(theme: Theme): Promise<void> {
  await preferences.setTheme(theme)
}

async function chooseCalendar(calendar: CalendarSystem): Promise<void> {
  await preferences.setCalendar(calendar)
}

function calendarLabel(calendar: CalendarSystem): string {
  return 'persian' === calendar ? t('settings.calendarPersian') : t('settings.calendarGregorian')
}

/**
 * Today's date written in each calendar, next to its name.
 *
 * Far more useful than the name on its own: "Shamsi (Solar Hijri)" means
 * nothing to somebody who has never seen one, while "۲۴ شهریور ۱۴۰۵" next to
 * "15 September 2026" explains the setting completely.
 */
function sampleDate(calendar: CalendarSystem): string {
  return formatDate(todayIso(), locale.value, calendar)
}

// --- Password -------------------------------------------------------------

const password = reactive({
  current: '',
  next: '',
  repeated: '',
})

const passwordError = ref('')
const passwordViolations = ref<Record<string, string>>({})
const passwordSaved = ref(false)
const passwordBusy = ref(false)

async function changePassword(): Promise<void> {
  passwordError.value = ''
  passwordViolations.value = {}
  passwordSaved.value = false

  // Compared here rather than at the server: a mismatch is a typo in this form,
  // and nothing the server knows changes the answer.
  if (password.next !== password.repeated) {
    passwordError.value = t('settings.passwordsDiffer')
    return
  }

  passwordBusy.value = true

  try {
    await api.changePassword({ currentPassword: password.current, newPassword: password.next })
    passwordSaved.value = true

    // Cleared on success and not before. Leaving a password sitting in a form
    // after it has been changed is one shoulder-glance from being read, and
    // clearing it on failure would mean retyping all three over one typo.
    password.current = ''
    password.next = ''
    password.repeated = ''
  } catch (e) {
    passwordError.value = apiMessage(e, 'settings.passwordChangeFailed')
    if (e instanceof ApiError) passwordViolations.value = e.violations
  } finally {
    passwordBusy.value = false
  }
}
</script>

<template>
  <div class="page">
    <h1>{{ t('settings.title') }}</h1>
    <p class="muted intro">{{ t('settings.intro') }}</p>

    <div class="grid grid-2">
      <!-- ---------- Language ---------- -->
      <section class="card">
        <h2>{{ t('settings.languageTitle') }}</h2>
        <p class="muted small note">{{ t('settings.languageIntro') }}</p>

        <div class="options" role="radiogroup" :aria-label="t('settings.languageTitle')">
          <button
            v-for="code in SUPPORTED_LOCALES"
            :key="code"
            type="button"
            role="radio"
            :aria-checked="preferences.locale === code"
            class="option"
            :class="{ 'option-active': preferences.locale === code }"
            @click="chooseLocale(code)"
          >
            <!-- Each language names itself. Someone looking for German is
                 looking for "Deutsch", not for the word "German" in a language
                 they cannot read. -->
            <span class="option-label">{{ LOCALE_ENDONYMS[code] }}</span>
            <span v-if="preferences.locale === code" class="tick" aria-hidden="true">✓</span>
          </button>
        </div>
      </section>

      <!-- ---------- Appearance ---------- -->
      <section class="card">
        <h2>{{ t('settings.themeTitle') }}</h2>
        <p class="muted small note">{{ t('settings.themeIntro') }}</p>

        <div class="options" role="radiogroup" :aria-label="t('settings.themeTitle')">
          <button
            v-for="option in THEMES"
            :key="option"
            type="button"
            role="radio"
            :aria-checked="preferences.theme === option"
            class="option"
            :class="{ 'option-active': preferences.theme === option }"
            @click="chooseTheme(option)"
          >
            <span class="option-icon" aria-hidden="true">{{ themeIcons[option] }}</span>
            <span class="option-label">{{ themeLabel(option) }}</span>
            <span v-if="preferences.theme === option" class="tick" aria-hidden="true">✓</span>
          </button>
        </div>

        <p v-if="preferences.theme === 'system'" class="muted small hint">
          {{ t('settings.themeSystemHint') }}
        </p>
      </section>

      <!-- ---------- Calendar ---------- -->
      <section class="card">
        <h2>{{ t('settings.calendarTitle') }}</h2>
        <p class="muted small note">{{ t('settings.calendarIntro') }}</p>

        <div class="options" role="radiogroup" :aria-label="t('settings.calendarTitle')">
          <button
            v-for="option in CALENDARS"
            :key="option"
            type="button"
            role="radio"
            :aria-checked="preferences.calendar === option"
            class="option"
            :class="{ 'option-active': preferences.calendar === option }"
            @click="chooseCalendar(option)"
          >
            <span class="option-label">
              {{ calendarLabel(option) }}
              <span class="muted small sample">{{ sampleDate(option) }}</span>
            </span>
            <span v-if="preferences.calendar === option" class="tick" aria-hidden="true">✓</span>
          </button>
        </div>

        <p class="muted small hint">{{ t('settings.calendarHint') }}</p>
      </section>

      <!-- ---------- Password ---------- -->
      <section class="card">
        <h2>{{ t('settings.passwordTitle') }}</h2>
        <p class="muted small note">{{ t('settings.passwordIntro') }}</p>

        <form class="stack" @submit.prevent="changePassword">
          <div>
            <label for="current-password">{{ t('settings.currentPassword') }}</label>
            <input
              id="current-password"
              v-model="password.current"
              type="password"
              autocomplete="current-password"
              required
            />
            <p v-if="passwordViolations.currentPassword" class="field-error">
              {{ passwordViolations.currentPassword }}
            </p>
          </div>

          <div>
            <label for="new-password">{{ t('settings.newPassword') }}</label>
            <input
              id="new-password"
              v-model="password.next"
              type="password"
              autocomplete="new-password"
              required
            />
            <p class="muted small hint">{{ t('auth.passwordHint') }}</p>
            <p v-if="passwordViolations.newPassword" class="field-error">
              {{ passwordViolations.newPassword }}
            </p>
          </div>

          <div>
            <label for="repeat-password">{{ t('settings.repeatPassword') }}</label>
            <input
              id="repeat-password"
              v-model="password.repeated"
              type="password"
              autocomplete="new-password"
              required
            />
          </div>

          <p v-if="passwordError" class="alert alert-error">{{ passwordError }}</p>
          <p v-if="passwordSaved" class="alert alert-success">
            {{ t('settings.passwordChanged') }}
          </p>

          <button type="submit" :disabled="passwordBusy">
            {{ passwordBusy ? t('settings.changingPassword') : t('settings.changePassword') }}
          </button>
        </form>

        <!-- Said before it happens, not after: being signed out of a phone is a
             surprise worth warning about rather than reporting. -->
        <p class="muted small hint">{{ t('settings.passwordSessionsHint') }}</p>
      </section>
    </div>

    <!-- Shown when the change could not be written to the account. It still
         applied locally, so this is a note rather than an error. -->
    <p v-if="preferences.error" class="alert alert-info small footer-note">
      {{ t('settings.offlineNote') }}
    </p>
  </div>
</template>

<style scoped>
.intro { margin: 0 0 20px; max-width: 60ch; }
.note { margin: 0 0 16px; }
.hint { margin: 12px 0 0; }

.options { display: flex; flex-direction: column; gap: 8px; }

.option {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  text-align: start;
  padding: 12px 14px;
  background: var(--surface-2);
  color: var(--text);
  border: 1px solid transparent;
  font-weight: 500;
}

.option:hover { border-color: var(--accent); }

/* The selected option is marked by a border and a tick, not by colour alone. */
.option-active {
  border-color: var(--accent);
  background: var(--surface);
}

.option-icon { font-size: 16px; line-height: 1; }
.option-label { flex: 1; }

/* The date under the calendar's name, so the setting explains itself. */
.sample { display: block; margin-top: 2px; }
.tick { color: var(--accent); font-weight: 700; }

.footer-note { margin-top: 16px; }
</style>
