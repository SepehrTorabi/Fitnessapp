<script setup lang="ts">
import { computed } from 'vue'
import type { Nutrients } from '@/api/types'

/**
 * Protein, carbohydrate and fat against their targets.
 *
 * Three progress bars rather than a pie: the question is "how far through each
 * target am I", which is a magnitude comparison against a known maximum, and a
 * pie cannot show overshoot at all.
 */
const props = defineProps<{
  consumed: Nutrients
  target: Nutrients | null
}>()

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
    label: 'Protein',
    color: 'var(--protein)',
    consumed: props.consumed.proteinG,
    target: props.target?.proteinG ?? null,
  },
  {
    key: 'carbs',
    label: 'Carbs',
    color: 'var(--carbs)',
    consumed: props.consumed.carbsG,
    target: props.target?.carbsG ?? null,
  },
  {
    key: 'fat',
    label: 'Fat',
    color: 'var(--fat)',
    consumed: props.consumed.fatG,
    target: props.target?.fatG ?? null,
  },
])

/** Capped at 100% so an overshoot does not draw outside the track. */
function fillPercent(row: Row): number {
  if (row.target === null || row.target <= 0) return 0

  return Math.min(100, (row.consumed / row.target) * 100)
}

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
          {{ Math.round(row.consumed) }}<span class="muted"> / {{ row.target === null ? '—' : Math.round(row.target) }} g</span>
        </span>
      </div>

      <div
        class="track"
        role="progressbar"
        :aria-valuenow="Math.round(row.consumed)"
        :aria-valuemin="0"
        :aria-valuemax="row.target ?? undefined"
        :aria-label="row.label"
      >
        <div class="fill" :style="{ width: `${fillPercent(row)}%`, background: row.color }"></div>
      </div>

      <p v-if="isOver(row)" class="small over-note">
        {{ Math.round(row.consumed - (row.target ?? 0)) }} g over target
      </p>
    </div>
  </div>
</template>

<style scoped>
.macros { display: flex; flex-direction: column; gap: 14px; }
.macro-head { margin-bottom: 5px; }
.macro-label { font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 7px; }
.macro-value { font-size: 13px; font-variant-numeric: tabular-nums; }

.dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }

.track {
  height: 8px;
  background: var(--surface-2);
  border-radius: 999px;
  overflow: hidden;
}

.fill {
  height: 100%;
  border-radius: 999px;
  transition: width 0.25s ease;
}

.over-note { color: var(--over); margin: 4px 0 0; }
</style>
