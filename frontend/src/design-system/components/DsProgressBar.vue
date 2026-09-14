<script setup lang="ts">
import { computed } from 'vue'
import styles from '../css/ProgressBar.module.css'

/**
 * The design system's linear ProgressBar.
 *
 * `value` and `max` are passed rather than a percentage so the element can carry
 * the real numbers in its ARIA attributes - a screen reader should hear
 * "135 of 160 grams", not "84 percent".
 *
 * The fill is clamped at 100%: going over target is reported in words next to
 * the bar, because a bar drawn past the end of its own track reads as a
 * rendering fault rather than as information.
 */
const props = withDefaults(
  defineProps<{
    value: number
    max: number | null
    status?: 'info' | 'success' | 'warning' | 'error'
    size?: 'small' | 'medium' | 'large'
    label?: string
    /**
     * Tints the bar, for the per-macro colours.
     *
     * Applied through the stylesheet's own --progress-bar-* custom properties,
     * which is the extension point its status variants use, rather than by
     * overriding `background` on the fill - that would leave the track at
     * whichever status colour it had and the two would disagree.
     */
    color?: string
  }>(),
  { status: 'info', size: 'small', label: undefined, color: undefined },
)

/**
 * The track is derived from the fill rather than picked separately, so a new
 * macro colour cannot end up with a track that does not belong to it.
 */
const tint = computed(() =>
  props.color
    ? {
        '--progress-bar-fill-color': props.color,
        '--progress-bar-track-color': `color-mix(in srgb, ${props.color} 16%, transparent)`,
      }
    : {},
)

const percent = computed(() => {
  if (props.max === null || props.max <= 0) return 0

  return Math.min(100, Math.max(0, (props.value / props.max) * 100))
})
</script>

<template>
  <div
    :class="[styles.bar, styles[`bar--${size}`], styles[`bar--${status}`]]"
    role="progressbar"
    :aria-valuenow="Math.round(value)"
    :aria-valuemin="0"
    :aria-valuemax="max ?? undefined"
    :aria-label="label"
    :style="tint"
  >
    <div :class="styles.bar__track">
      <div :class="styles.bar__fill" :style="{ width: `${percent}%` }" />
    </div>
  </div>
</template>
