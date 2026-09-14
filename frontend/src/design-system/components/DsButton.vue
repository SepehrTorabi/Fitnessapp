<script setup lang="ts">
import { computed } from 'vue'
import styles from '../css/Button.module.css'

/**
 * The design system's Button.
 *
 * Renders a <button> normally, or an <a> when `href` is given - a download link
 * and a submit button should look identical, and making the caller choose the
 * element keeps the semantics right instead of hanging a click handler on a
 * <button> that is really navigation.
 */
const props = withDefaults(
  defineProps<{
    variant?: 'primary' | 'outline' | 'transparent' | 'ghost' | 'subtle'
    size?: 'sm' | 'md' | 'lg'
    fullWidth?: boolean
    disabled?: boolean
    /** Renders an anchor instead of a button. */
    href?: string
    type?: 'button' | 'submit' | 'reset'
  }>(),
  { variant: 'primary', size: 'md', fullWidth: false, disabled: false, href: undefined, type: 'button' },
)

const classes = computed(() => [
  styles.button,
  styles[`button--${props.variant}`],
  styles[`button--${props.size}`],
  props.fullWidth && styles['button--fullWidth'],
])
</script>

<template>
  <a v-if="href" :class="classes" :href="href"><slot /></a>
  <button v-else :class="classes" :type="type" :disabled="disabled"><slot /></button>
</template>
