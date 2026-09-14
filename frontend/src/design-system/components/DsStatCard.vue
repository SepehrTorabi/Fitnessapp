<script setup lang="ts">
import styles from '../css/StatCard.module.css'

/**
 * The design system's StatCard: a label, one large value, and an optional
 * trend line underneath.
 *
 * `value` is a string rather than a number because the caller decides how to
 * format and localise it - "2.445,4 kcal" is not something this component
 * should be deriving.
 */
withDefaults(
  defineProps<{
    label: string
    value: string
    /** Colours the trend line. Omit when there is nothing to compare against. */
    trend?: 'up' | 'down' | 'flat'
    trendLabel?: string
  }>(),
  { trend: undefined, trendLabel: undefined },
)
</script>

<template>
  <div :class="[styles.statCard, 'ds-statcard']">
    <div :class="styles.statCard__top">
      <span :class="styles.statCard__label">{{ label }}</span>
    </div>

    <div :class="styles.statCard__value">{{ value }}</div>

    <div
      v-if="trend || trendLabel"
      :class="[styles.statCard__trend, trend && styles[`statCard__trend--${trend}`]]"
    >
      <span v-if="trendLabel" :class="styles.statCard__trendLabel">{{ trendLabel }}</span>
    </div>
  </div>
</template>

<style scoped>
/*
 * Works around a gap in the vendored StatCard.module.css, without editing it.
 *
 * Its .statCard__label is painted with --text-disabled. The label is not a
 * disabled control, and in dark mode that token resolves to #44445E, which
 * measures 1.68:1 against the card - unreadable.
 *
 * --text-muted would be the obvious replacement, but its dark value is only
 * 3.07:1, still under the 4.5:1 that 12px text needs. --text-body is what every
 * other piece of secondary text on these pages already uses and reads 7.8:1, so
 * the disabled colour is remapped to that for the span of this component.
 *
 * Nothing inside a StatCard is ever genuinely disabled, so nothing else is
 * affected. Remove this once the label is corrected upstream.
 */
.ds-statcard {
  --text-disabled: var(--text-body);
}
</style>
