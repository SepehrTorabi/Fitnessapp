<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { usePreferencesStore } from '@/stores/preferences'
import { LOCALE_ENDONYMS, SUPPORTED_LOCALES, type SupportedLocale } from '@/i18n'
import { THEMES, type Theme } from '@/theme'

/**
 * Language and theme in two compact selects.
 *
 * Used on the screens that exist before there is an account - sign in, sign up,
 * confirming an address, choosing a new password - where the full settings page
 * is out of reach. Someone who cannot read the sign-up form cannot sign up, and
 * someone who needs a dark screen needs it before they have an account as much
 * as after.
 *
 * The calendar used to be a third select here and deliberately is not any more.
 * Nothing on these screens shows a date, so the setting had nothing to act on
 * and was three words of jargon in front of somebody trying to sign in. It
 * still follows the language on its own - picking Farsi moves it to Shamsi, as
 * it always did - and it is a real choice on the settings page, once there is
 * an account to attach it to and dates on screen to see it applied to.
 *
 * Nothing is written to the server here, and nothing needs to be: the
 * preferences store already skips the network call while signed out, keeps the
 * choices in localStorage, and the registration form sends them along - the
 * calendar included - so they survive into the new account.
 *
 * Plain <select> elements rather than the settings page's button groups, on
 * purpose: this has to fit in a corner without taking over the page, and the
 * native control is already keyboard accessible, screen-reader labelled and
 * sized for touch on a phone.
 */
const { t } = useI18n()
const preferences = usePreferencesStore()

function themeLabel(theme: Theme): string {
  return t(`settings.theme${theme.charAt(0).toUpperCase()}${theme.slice(1)}` as never)
}

function onLocale(event: Event): void {
  void preferences.setLocale((event.target as HTMLSelectElement).value as SupportedLocale)
}

function onTheme(event: Event): void {
  void preferences.setTheme((event.target as HTMLSelectElement).value as Theme)
}
</script>

<template>
  <div class="appearance">
    <label class="control">
      <span class="visually-hidden">{{ t('settings.languageTitle') }}</span>
      <select :value="preferences.locale" @change="onLocale">
        <option v-for="code in SUPPORTED_LOCALES" :key="code" :value="code">
          <!-- Each language names itself: a picker has to be readable by
               somebody who cannot read the language currently on screen. -->
          {{ LOCALE_ENDONYMS[code] }}
        </option>
      </select>
    </label>

    <label class="control">
      <span class="visually-hidden">{{ t('settings.themeTitle') }}</span>
      <select :value="preferences.theme" @change="onTheme">
        <option v-for="option in THEMES" :key="option" :value="option">
          {{ themeLabel(option) }}
        </option>
      </select>
    </label>
  </div>
</template>

<style scoped>
.appearance {
  display: flex;
  gap: var(--spacing-2xs);
  flex-wrap: wrap;
  align-items: center;
}

.control { margin: 0; }

.control select {
  width: auto;
  padding: var(--spacing-3xs) var(--spacing-2xs);
  font-size: var(--fontsize-body-small);
  background: var(--surface-primary);
}
</style>
