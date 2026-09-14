<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DaySummary } from '@/api/types'

/**
 * Calories eaten per day against that day's budget.
 *
 * A bar per day for what was eaten, and a marker at the budget. Under budget is
 * green, over is red - but the colour is never the only signal: the bar visibly
 * crosses the marker, the hover readout spells out the difference, and there is
 * a table view underneath. Red and green are the one pair a red-green colourblind
 * reader cannot separate, so the whole chart has to work without them.
 *
 * Drawn as plain SVG rather than with a charting library: seven bars and a
 * marker do not justify the dependency, and the maths is worth seeing.
 */
const props = defineProps<{ days: DaySummary[] }>()

const { t, locale } = useI18n()

// A fixed drawing grid. The SVG scales to its container, so these are
// proportions rather than pixels on screen.
const W = 700
const H = 260
const PAD = { top: 24, right: 12, bottom: 42, left: 48 }

const plotW = W - PAD.left - PAD.right
const plotH = H - PAD.top - PAD.bottom

const hovered = ref<number | null>(null)
const showTable = ref(false)

/** Headroom above the tallest thing drawn, so nothing touches the top edge. */
const maxValue = computed(() => {
  const values = props.days.flatMap((d) => [d.consumed.kcal, d.budgetKcal ?? 0])
  const peak = Math.max(...values, 1)

  return peak * 1.15
})

const bandWidth = computed(() => plotW / Math.max(props.days.length, 1))
const barWidth = computed(() => Math.min(48, bandWidth.value * 0.55))

function y(value: number): number {
  return PAD.top + plotH - (value / maxValue.value) * plotH
}

function bandCenter(index: number): number {
  return PAD.left + bandWidth.value * (index + 0.5)
}

/** Four gridlines is enough to read a value off without fencing in the bars. */
const ticks = computed(() => {
  const step = niceStep(maxValue.value / 4)
  const out: number[] = []

  for (let value = 0; value <= maxValue.value; value += step) {
    out.push(Math.round(value))
  }

  return out
})

/** Round a raw step up to something a person would actually label. */
function niceStep(raw: number): number {
  const magnitude = 10 ** Math.floor(Math.log10(Math.max(raw, 1)))
  const normalized = raw / magnitude

  const step = normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10

  return step * magnitude
}

// Formatted with the app's language rather than the browser's, so switching to
// German turns "Mon" into "Mo" along with everything else on the page.
function weekday(iso: string): string {
  return new Date(`${iso}T00:00:00`).toLocaleDateString(locale.value, { weekday: 'short' })
}

function dayNumber(iso: string): string {
  return new Date(`${iso}T00:00:00`).toLocaleDateString(locale.value, {
    day: 'numeric',
    month: 'numeric',
  })
}

function barFill(day: DaySummary): string {
  // No target means nothing to compare against, so the bar stays neutral rather
  // than claiming the day was fine.
  if (day.withinBudget === null) return 'var(--neutral)'

  return day.withinBudget ? 'var(--chart-good)' : 'var(--chart-over)'
}

function difference(day: DaySummary): string {
  if (day.remainingKcal === null) return t('chart.noTarget')

  const kcal = Math.round(Math.abs(day.remainingKcal))

  return day.remainingKcal >= 0 ? t('chart.kcalLeft', { kcal }) : t('chart.kcalOver', { kcal })
}

const anyOverBudget = computed(() => props.days.some((d) => d.withinBudget === false))
const anyWithinBudget = computed(() => props.days.some((d) => d.withinBudget === true))
</script>

<template>
  <figure class="chart">
    <figcaption class="row-between">
      <div>
        <h3>{{ t('chart.title') }}</h3>
        <p class="muted small caption">{{ t('chart.caption') }}</p>
      </div>

      <button class="secondary small-btn" type="button" @click="showTable = !showTable">
        {{ showTable ? t('chart.showChart') : t('chart.showTable') }}
      </button>
    </figcaption>

    <!-- Identity is never colour alone: a legend names each state in words. -->
    <div class="legend" aria-hidden="true">
      <span v-if="anyWithinBudget" class="legend-item">
        <span class="swatch swatch-good"></span>{{ t('chart.withinBudget') }}
      </span>
      <span v-if="anyOverBudget" class="legend-item">
        <span class="swatch swatch-over"></span>{{ t('chart.overBudget') }}
      </span>
      <span class="legend-item"><span class="swatch swatch-target"></span>{{ t('chart.budgetLine') }}</span>
    </div>

    <div v-if="!showTable" class="plot-wrap">
      <svg
        :viewBox="`0 0 ${W} ${H}`"
        class="plot"
        role="img"
        :aria-label="t('chart.ariaLabel')"
      >
        <!-- Gridlines sit behind the data and stay recessive. -->
        <g class="grid">
          <template v-for="tick in ticks" :key="tick">
            <line :x1="PAD.left" :x2="W - PAD.right" :y1="y(tick)" :y2="y(tick)" />
            <text :x="PAD.left - 8" :y="y(tick) + 4" class="tick-label">{{ tick }}</text>
          </template>
        </g>

        <!-- The hover handlers sit on the group, not on the transparent band
             below: the bar is painted on top of that band and would otherwise
             swallow the event, and a sibling's handler never sees it. On the
             group, whichever child is hit lets the event bubble up. -->
        <g
          v-for="(day, index) in days"
          :key="day.date"
          @mouseenter="hovered = index"
          @mouseleave="hovered = null"
        >
          <!-- A transparent band the full height of the plot, so the hover
               target is the whole column rather than just the bar. -->
          <rect
            :x="PAD.left + bandWidth * index"
            :y="PAD.top"
            :width="bandWidth"
            :height="plotH"
            fill="transparent"
          />

          <rect
            :x="bandCenter(index) - barWidth / 2"
            :y="y(day.consumed.kcal)"
            :width="barWidth"
            :height="Math.max(plotH + PAD.top - y(day.consumed.kcal), 0)"
            :fill="barFill(day)"
            :opacity="hovered === null || hovered === index ? 1 : 0.45"
            rx="4"
            class="bar"
          />

          <!-- The budget marker. This is what makes over/under readable without
               relying on the colour at all. -->
          <line
            v-if="day.budgetKcal !== null"
            :x1="bandCenter(index) - barWidth / 2 - 5"
            :x2="bandCenter(index) + barWidth / 2 + 5"
            :y1="y(day.budgetKcal)"
            :y2="y(day.budgetKcal)"
            class="target"
          />

          <text :x="bandCenter(index)" :y="H - 22" class="axis-label">{{ weekday(day.date) }}</text>
          <text :x="bandCenter(index)" :y="H - 8" class="axis-sublabel">{{ dayNumber(day.date) }}</text>
        </g>
      </svg>

      <!-- Readout rather than a floating tooltip: it never clips at the edge of
           the chart and it stays legible on a touch screen. -->
      <div class="readout" :class="{ 'readout-idle': hovered === null }">
        <template v-if="hovered !== null && days[hovered]">
          <strong>{{ weekday(days[hovered].date) }} {{ dayNumber(days[hovered].date) }}</strong>
          <span>{{ t('chart.eaten', { kcal: Math.round(days[hovered].consumed.kcal) }) }}</span>
          <span v-if="days[hovered].budgetKcal !== null">
            {{ t('chart.ofBudget', { kcal: Math.round(days[hovered].budgetKcal!) }) }}
          </span>
          <span
            class="badge"
            :class="days[hovered].withinBudget === false ? 'badge-over' : 'badge-good'"
          >
            {{ difference(days[hovered]) }}
          </span>
        </template>
        <span v-else class="muted small">{{ t('chart.hoverHint') }}</span>
      </div>
    </div>

    <!-- The same data as text. Required relief for the contrast warning on the
         bar fills, and the only version a screen reader can actually read. -->
    <table v-else>
      <thead>
        <tr>
          <th>{{ t('chart.tableDay') }}</th>
          <th class="num">{{ t('chart.tableEaten') }}</th>
          <th class="num">{{ t('chart.tableBudget') }}</th>
          <th class="num">{{ t('chart.tableDifference') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="day in days" :key="day.date">
          <td>{{ weekday(day.date) }} {{ dayNumber(day.date) }}</td>
          <td class="num">{{ Math.round(day.consumed.kcal) }}</td>
          <td class="num">{{ day.budgetKcal === null ? '—' : Math.round(day.budgetKcal) }}</td>
          <td class="num">
            <span
              class="badge"
              :class="day.withinBudget === false ? 'badge-over' : day.withinBudget ? 'badge-good' : ''"
            >
              {{ difference(day) }}
            </span>
          </td>
        </tr>
      </tbody>
    </table>
  </figure>
</template>

<style scoped>
.chart { margin: 0; }
.caption { margin: 0; }
.small-btn { padding: 5px 11px; font-size: 13px; }

.legend {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  margin: 10px 0 4px;
  font-size: 13px;
  color: var(--text-muted);
}

.legend-item { display: inline-flex; align-items: center; gap: 6px; }

.swatch {
  width: 11px;
  height: 11px;
  border-radius: 3px;
  display: inline-block;
}

.swatch-good { background: var(--chart-good); }
.swatch-over { background: var(--chart-over); }
.swatch-target { background: var(--text-muted); height: 2px; width: 14px; border-radius: 1px; }

.plot { width: 100%; height: auto; display: block; }

.grid line {
  stroke: var(--border);
  stroke-width: 1;
}

.tick-label {
  fill: var(--text-muted);
  font-size: 11px;
  text-anchor: end;
  font-variant-numeric: tabular-nums;
}

.bar { transition: opacity 0.12s ease; }

.target {
  stroke: var(--text-muted);
  stroke-width: 2;
  stroke-linecap: round;
}

.axis-label {
  fill: var(--text);
  font-size: 12px;
  font-weight: 600;
  text-anchor: middle;
}

.axis-sublabel {
  fill: var(--text-muted);
  font-size: 11px;
  text-anchor: middle;
}

.readout {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
  min-height: 30px;
  padding: 6px 10px;
  margin-top: 6px;
  border-radius: var(--radius-sm);
  background: var(--surface-2);
  font-size: 13px;
}

.readout-idle { background: transparent; }
</style>
