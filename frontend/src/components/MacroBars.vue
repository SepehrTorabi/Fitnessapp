<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { Nutrients } from '@/api/types'
import { useNumbers } from '@/composables/useNumbers'
import { DsProgressBar } from '@/design-system/components'

/**
 * Protein, carbohydrate and fat against their targets.
 *
 * Three progress bars rather than a pie: the question is "how far through each
 * target am I", which is a magnitude comparison against a known maximum, and a
 * pie cannot show overshoot at all.
 *
 * The bars are the design system's ProgressBar, tinted per macro. The three
 * tints come from its accent ramps and were checked as a trio - the closest
 * pair is ΔE 17.7 under deuteranopia - but each bar is labelled in words
 * anyway, so the colour is recognition rather than the only way to read it.
 */
const props = defineProps<{
  consumed: Nutrients
  target: Nutrients | null
}>()

const { t } = useI18n()
const { n } = useNumbers()

interface Row {
  key: string
  label: string
  color: string
  consumed: number
  target: number | null
}

const rows = computed<Row[]>(() => [
  {
    key: 'protein',
    label: t('macros.protein'),
    color: 'var(--protein)',
    consumed: props.consumed.proteinG,
    target: props.target?.proteinG ?? null,
  },
  {
    key: 'carbs',
    label: t('macros.carbs'),
    color: 'var(--carbs)',
    consumed: props.consumed.carbsG,
    target: props.target?.carbsG ?? null,
  },
  {
    key: 'fat',
    label: t('macros.fat'),
    color: 'var(--fat)',
    consumed: props.consumed.fatG,
    target: props.target?.fatG ?? null,
  },
])

function isOver(row: Row): boolean {
  return row.target !== null && row.consumed > row.target
}
</script>

<template>
  <div class="macros">
    <div v-for="row in rows" :key="row.key" class="macro">
      <div class="row-between macro-head">
        <span class="macro-label">
          <span class="dot" :style="{ background: row.color }"></span>{{ row.label }}
        </span>
        <span class="macro-value">
          <!-- The unit comes from the catalogue rather than being typed as "g":
               in Farsi it is گرم, and a stray Latin letter in the middle of a
               right-to-left line is exactly the kind of detail that makes a
               translated interface feel half-translated. -->
          {{ n(row.consumed) }}<span class="muted"> / {{ n(row.target) }} {{ t('common.grams') }}</span>
        </span>
      </div>

      <DsProgressBar
        :value="row.consumed"
        :max="row.target"
        :color="row.color"
        :label="row.label"
        size="medium"
      />

      <p v-if="isOver(row)" class="small over-note">
        {{ t('macros.overTarget', { grams: n(row.consumed - (row.target ?? 0)) }) }}
      </p>
    </div>
  </div>
</template>

<style scoped>
.macros { display: flex; flex-direction: column; gap: var(--spacing-medium); }
.macro-head { margin-bottom: var(--spacing-3xs); }

.macro-label {
  font-size: var(--fontsize-body-small);
  font-weight: var(--type-font-weight-semi-bold);
  color: var(--text-headings);
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-2xs);
}

.macro-value {
  font-size: var(--fontsize-body-small);
  font-variant-numeric: tabular-nums;
  color: var(--text-headings);
}

.dot {
  width: var(--scale-200);
  height: var(--scale-200);
  border-radius: var(--border-radius-round);
  display: inline-block;
}

.over-note { color: var(--text-error); margin: var(--spacing-3xs) 0 0; }
</style>
