<script setup lang="ts">
import styles from '../css/Card.module.css'

/**
 * The design system's Card.
 *
 * A wrapper, not a re-implementation: every class comes from the vendored
 * Card.module.css, so the card looks the way the design system says it does and
 * follows it when that file is refreshed.
 *
 * Only the content-slot form is exposed. The React original can also build its
 * own image/title/badges/action layout from props; this app always supplies its
 * own content, and porting a layout nothing uses would be code to keep in step
 * with upstream for no benefit.
 */
withDefaults(
  defineProps<{
    /** Removes the card's inner padding, for content that manages its own. */
    noPadding?: boolean
  }>(),
  { noPadding: false },
)
</script>

<template>
  <div :class="[styles.card, noPadding && styles['card--noPadding']]">
    <!-- .card itself is padding: 0 by design; the padding and the vertical
         rhythm between blocks live in .card__body, which the React original
         always renders around its content. Skipping it left content sitting
         against the border. -->
    <div v-if="!noPadding" :class="styles.card__body"><slot /></div>
    <slot v-else />
  </div>
</template>
