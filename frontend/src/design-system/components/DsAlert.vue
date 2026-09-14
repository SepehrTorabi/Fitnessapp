<script setup lang="ts">
import styles from '../css/Alert.module.css'

/**
 * The design system's Alert.
 *
 * The icon slot is kept even when empty: the stylesheet reserves that column, so
 * dropping it would shift the text out of alignment with every other alert on
 * the page.
 */
withDefaults(
  defineProps<{
    status?: 'info' | 'success' | 'warning' | 'error'
    title?: string
  }>(),
  { status: 'info', title: undefined },
)
</script>

<template>
  <div
    :class="[styles.alert, styles[`alert--${status}`]]"
    :role="status === 'error' ? 'alert' : 'status'"
    :aria-live="status === 'error' ? 'assertive' : 'polite'"
    aria-atomic="true"
  >
    <span :class="styles.alert__icon" aria-hidden="true"><slot name="icon" /></span>
    <div :class="styles.alert__body">
      <span v-if="title" :class="styles.alert__title">{{ title }}</span>
      <span :class="styles.alert__description"><slot /></span>
    </div>
  </div>
</template>
