<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { usePreferencesStore } from '@/stores/preferences'
import { LOCALE_ENDONYMS, SUPPORTED_LOCALES, type SupportedLocale } from '@/i18n'
import { THEMES, type Theme } from '@/theme'

/**
 * Language and appearance.
 *
 * Both apply the moment they are clicked and are written to the account in the
 * background - there is no Save button, because there is nothing to review. A
 * setting whose effect you can see immediately does not need confirming.
 */
const { t } = useI18n()
const preferences = usePreferencesStore()

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
  text-align: left;
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
.tick { color: var(--accent); font-weight: 700; }

.footer-note { margin-top: 16px; }
</style>
