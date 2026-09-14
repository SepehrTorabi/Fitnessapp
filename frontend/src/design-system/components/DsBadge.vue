<script setup lang="ts">
import { computed } from 'vue'
import styles from '../css/Badge.module.css'

export type BadgeStatus = 'default' | 'success' | 'error' | 'info' | 'warning'

/**
 * The design system's Badge.
 *
 * The 'status' type carries a dot next to the text. That matters here rather
 * than being decoration: it is a second, non-colour channel for the same
 * meaning, which is what keeps a red/green distinction readable for someone who
 * cannot tell the two apart.
 */
const props = withDefaults(
  defineProps<{
    label: string
    status?: BadgeStatus
    type?: 'text' | 'status'
  }>(),
  { status: 'default', type: 'text' },
)

const classes = computed(() =>
  props.type === 'status'
    ? [styles.badge, styles['badge--status'], styles[`badge--status--${props.status}`]]
    : [styles.badge, styles[`badge--${props.status}`]],
)
</script>

<template>
  <span :class="classes">
    <span v-if="type === 'status'" :class="styles['badge__status-dot']" aria-hidden="true" />
    {{ label }}
  </span>
</template>
