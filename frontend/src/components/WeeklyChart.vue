<script setup lang="ts">
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DaySummary } from '@/api/types'
import { usePreferencesStore } from '@/stores/preferences'
import { formatShortDate, formatWeekday } from '@/calendar'
import { useNumbers } from '@/composables/useNumbers'

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
 *
 * The chart is also the dashboard's day selector. Clicking a bar is the obvious
 * gesture for "show me that day", and it is what the rest of the page listens
 * to - so every bar is a real control: focusable, operable with Enter or Space,
 * and labelled with the day it stands for.
 *
 * Right-to-left is the one thing SVG cannot inherit from the page. A mirrored
 * layout means the week has to run the other way and the value axis has to move
 * to the other side, and no amount of `dir="rtl"` on an ancestor will move a
 * coordinate. So the geometry reads the direction and mirrors itself.
 */
const props = withDefaults(
  defineProps<{
    days: DaySummary[]
    /** The day the dashboard is currently showing, as an ISO date. */
    selected?: string | null
  }>(),
  { selected: null },
)

const emit = defineEmits<{ select: [date: string] }>()

const { t, locale } = useI18n()
const preferences = usePreferencesStore()
const { n } = useNumbers()

// A fixed drawing grid. The SVG scales to its container, so these are
// proportions rather than pixels on screen.
const W = 700
const H = 260

/** Room for the value axis on one side, and a hair of breathing room on the other. */
const AXIS_GUTTER = 52
const EDGE_GUTTER = 12
const PAD_TOP = 24
const PAD_BOTTOM = 42

const rtl = computed(() => preferences.direction === 'rtl')

// The value axis belongs on the side the reading starts from, which swaps with
// the direction. Everything below is expressed in terms of these two.
const padStart = computed(() => (rtl.value ? EDGE_GUTTER : AXIS_GUTTER))
const padEnd = computed(() => (rtl.value ? AXIS_GUTTER : EDGE_GUTTER))

const plotW = computed(() => W - padStart.value - padEnd.value)
const plotH = H - PAD_TOP - PAD_BOTTOM

const hovered = ref<number | null>(null)
const showTable = ref(false)

/** Headroom above the tallest thing drawn, so nothing touches the top edge. */
const maxValue = computed(() => {
  const values = props.days.flatMap((d) => [d.consumed.kcal, d.budgetKcal ?? 0])
  const peak = Math.max(...values, 1)

  return peak * 1.15
})

const bandWidth = computed(() => plotW.value / Math.max(props.days.length, 1))
const barWidth = computed(() => Math.min(48, bandWidth.value * 0.55))

function y(value: number): number {
  return PAD_TOP + plotH - (value / maxValue.value) * plotH
}

/**
 * Where a day sits along the axis.
 *
 * The one place the mirroring happens: in a right-to-left layout the earliest
 * day belongs on the right, so the index is flipped and every x coordinate in
 * the chart follows from here.
 */
function bandStart(index: number): number {
  const position = rtl.value ? props.days.length - 1 - index : index

  return padStart.value + bandWidth.value * position
}

function bandCenter(index: number): number {
  return bandStart(index) + bandWidth.value / 2
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

// Dates go through the calendar module, so the axis is labelled in whichever
// calendar the user reads - the bars are the same days either way.
function weekday(iso: string): string {
  return formatWeekday(iso, locale.value)
}

function dayNumber(iso: string): string {
  return formatShortDate(iso, locale.value, preferences.calendar)
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

  const formatted = n(kcal)

  return day.remainingKcal >= 0
    ? t('chart.kcalLeft', { kcal: formatted })
    : t('chart.kcalOver', { kcal: formatted })
}

function isSelected(day: DaySummary): boolean {
  return props.selected === day.date
}

/**
 * How prominent a bar is.
 *
 * Three states rather than two: with a day selected, the others recede - so the
 * chart shows at a glance which day the numbers below it belong to. Hovering
 * temporarily brings a bar back, because that is what the readout is describing.
 */
function barOpacity(day: DaySummary, index: number): number {
  if (hovered.value === index) return 1
  if (props.selected !== null) return isSelected(day) ? 1 : 0.4

  return hovered.value === null ? 1 : 0.45
}

function onKey(event: KeyboardEvent, date: string): void {
  if ('Enter' === event.key || ' ' === event.key) {
    event.preventDefault()
    emit('select', date)
  }
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
      <svg :viewBox="`0 0 ${W} ${H}`" class="plot" role="img" :aria-label="t('chart.ariaLabel')">
        <!-- Gridlines sit behind the data and stay recessive. -->
        <g class="grid">
          <template v-for="tick in ticks" :key="tick">
            <line :x1="padStart" :x2="W - padEnd" :y1="y(tick)" :y2="y(tick)" />
            <text
              :x="rtl ? W - padEnd + 8 : padStart - 8"
              :y="y(tick) + 4"
              class="tick-label"
              :text-anchor="rtl ? 'start' : 'end'"
            >
              {{ n(tick) }}
            </text>
          </template>
        </g>

        <!-- The hover and click handlers sit on the group, not on the transparent
             band below: the bar is painted on top of that band and would otherwise
             swallow the event, and a sibling's handler never sees it. On the
             group, whichever child is hit lets the event bubble up. -->
        <g
          v-for="(day, index) in days"
          :key="day.date"
          class="day-group"
          role="button"
          tabindex="0"
          :aria-pressed="isSelected(day)"
          :aria-label="t('chart.selectDay', { date: `${weekday(day.date)} ${dayNumber(day.date)}` })"
          @mouseenter="hovered = index"
          @mouseleave="hovered = null"
          @focus="hovered = index"
          @blur="hovered = null"
          @click="emit('select', day.date)"
          @keydown="onKey($event, day.date)"
        >
          <!-- A transparent band the full height of the plot, so the target is
               the whole column rather than just the bar. -->
          <rect
            :x="bandStart(index)"
            :y="PAD_TOP"
            :width="bandWidth"
            :height="plotH"
            :class="{ 'band-selected': isSelected(day) }"
            class="band"
          />

          <rect
            :x="bandCenter(index) - barWidth / 2"
            :y="y(day.consumed.kcal)"
            :width="barWidth"
            :height="Math.max(plotH + PAD_TOP - y(day.consumed.kcal), 0)"
            :fill="barFill(day)"
            :opacity="barOpacity(day, index)"
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

          <text
            :x="bandCenter(index)"
            :y="H - 22"
            class="axis-label"
            :class="{ 'axis-label-selected': isSelected(day) }"
          >
            {{ weekday(day.date) }}
          </text>
          <text :x="bandCenter(index)" :y="H - 8" class="axis-sublabel">{{ dayNumber(day.date) }}</text>
        </g>
      </svg>

      <!-- Readout rather than a floating tooltip: it never clips at the edge of
           the chart and it stays legible on a touch screen. -->
      <div class="readout" :class="{ 'readout-idle': hovered === null }">
        <template v-if="hovered !== null && days[hovered]">
          <strong>{{ weekday(days[hovered].date) }} {{ dayNumber(days[hovered].date) }}</strong>
          <span>{{ t('chart.eaten', { kcal: n(days[hovered].consumed.kcal) }) }}</span>
          <span v-if="days[hovered].budgetKcal !== null">
            {{ t('chart.ofBudget', { kcal: n(days[hovered].budgetKcal) }) }}
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
         bar fills, and the only version a screen reader can actually read. The
         rows select a day too, so the table is a complete alternative rather
         than a read-only consolation. -->
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
        <tr
          v-for="day in days"
          :key="day.date"
          class="table-row"
          :class="{ 'row-selected': isSelected(day) }"
        >
          <td>
            <button type="button" class="ghost row-button" @click="emit('select', day.date)">
              {{ weekday(day.date) }} {{ dayNumber(day.date) }}
            </button>
          </td>
          <td class="num">{{ n(day.consumed.kcal) }}</td>
          <td class="num">{{ n(day.budgetKcal) }}</td>
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

/*
 * The one subtlety of drawing a chart inside a mirrored document.
 *
 * SVG's text-anchor is resolved against the *text* direction, not against the
 * coordinate system - so in a right-to-left page "start" means the right-hand
 * edge of the glyphs, and the axis labels get anchored the wrong way round and
 * hang back into the plot. These labels are bare numbers, which are a
 * left-to-right run in every script, so saying so fixes the anchoring and is
 * what the digits want anyway.
 */
.tick-label {
  fill: var(--text-muted);
  font-size: 11px;
  font-variant-numeric: tabular-nums;
  direction: ltr;
}

.day-group { cursor: pointer; }

/* The focus ring goes on the group rather than on the bar: a bar for a day with
   nothing logged has no height, and an outline around nothing is invisible. */
.day-group:focus { outline: none; }
.day-group:focus-visible .band { stroke: var(--border-focus); stroke-width: 2; }

.band { fill: transparent; }

/* The selected column is tinted, so which day the page is showing survives even
   when that day's bar is zero. */
.band-selected { fill: var(--surface-subtle); }

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

.axis-label-selected { fill: var(--accent); }

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

.row-selected { background: var(--surface-subtle); }

.row-button {
  padding: 0;
  font: inherit;
  font-weight: 600;
  color: var(--text-action);
  text-align: start;
}

.row-button:hover { color: var(--text-action-hover); text-decoration: underline; }
</style>
