<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePreferencesStore } from '@/stores/preferences'
import {
  addDays,
  addMonths,
  formatDate,
  formatDayNumber,
  formatMonthLabel,
  monthGrid,
  todayIso,
  weekdayHeadings,
} from '@/calendar'

/**
 * A date field that speaks whichever calendar the user reads.
 *
 * This exists because `<input type="date">` cannot. The native control is
 * excellent - it is localised, keyboard accessible and familiar - but it is
 * Gregorian, always, in every browser. Offering a Shamsi calendar and then
 * asking someone to pick 1405/06/24 out of a Gregorian grid would be a setting
 * that changes the labels and not the thing the labels are for.
 *
 * So the grid is drawn here. What is emitted is still a Gregorian ISO string,
 * exactly what the native input would have emitted and exactly what the API
 * expects - the calendar changes what the user counts in, never what is sent.
 */
const props = withDefaults(
  defineProps<{
    modelValue: string
    /** The latest selectable day, as an ISO date. Days after it are disabled. */
    max?: string
    label?: string
    id?: string
  }>(),
  { max: undefined, label: undefined, id: undefined },
)

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const { t, locale } = useI18n()
const preferences = usePreferencesStore()

const open = ref(false)
const root = ref<HTMLElement | null>(null)
const grid = ref<HTMLElement | null>(null)
const popover = ref<HTMLElement | null>(null)

/**
 * Which edge of the trigger the popover hangs from.
 *
 * It starts at the trigger's own leading edge, which is the natural reading
 * order, and flips when that would push it off the screen - which it does
 * whenever the field sits at the end of a row, as it does on both pages that
 * use it. Measured rather than configured: the caller should not have to know
 * where its own layout put the field, and the answer changes with the window
 * width anyway.
 */
const alignToEnd = ref(false)

/**
 * The month on screen, and the day the keyboard is on.
 *
 * Separate from the selected value: paging through months or arrowing around
 * must not change what is selected until the user actually commits. Held as its
 * own ISO date so that arrowing past the end of a month pages the grid for free.
 */
const cursor = ref(props.modelValue)

const calendar = computed(() => preferences.calendar)

const grid6 = computed(() => monthGrid(cursor.value, calendar.value))
const headings = computed(() => weekdayHeadings(locale.value, calendar.value))
const monthLabel = computed(() => formatMonthLabel(cursor.value, locale.value, calendar.value))

const displayValue = computed(() => formatDate(props.modelValue, locale.value, calendar.value))

function isDisabled(iso: string): boolean {
  return props.max !== undefined && iso > props.max
}

function dayNumber(iso: string): string {
  return formatDayNumber(iso, locale.value, calendar.value)
}

function select(iso: string): void {
  if (isDisabled(iso)) return

  emit('update:modelValue', iso)
  close()
}

function toggle(): void {
  open.value ? close() : show()
}

function show(): void {
  cursor.value = props.modelValue
  alignToEnd.value = false
  open.value = true

  void nextTick(() => {
    keepOnScreen()
    // Focus the day the keyboard should start on, so the picker is usable
    // without ever touching the mouse.
    focusCursor()
  })
}

/** Flip the popover to the other edge if it does not fit where it opened. */
function keepOnScreen(): void {
  const box = popover.value?.getBoundingClientRect()

  if (!box) return

  const margin = 8

  if (box.right > window.innerWidth - margin || box.left < margin) {
    alignToEnd.value = true
  }
}

function close(): void {
  open.value = false
}

/**
 * Move the cursor and keep the focused cell under the keyboard.
 *
 * The date is moved rather than the cell index, which is what makes crossing a
 * month boundary work: stepping off the end of the month simply produces a date
 * in the next one, and the grid re-renders around it.
 */
function move(days: number): void {
  cursor.value = addDays(cursor.value, days)
  void nextTick(() => focusCursor())
}

function page(months: number): void {
  cursor.value = addMonths(cursor.value, months, calendar.value)
  void nextTick(() => focusCursor())
}

function focusCursor(): void {
  grid.value?.querySelector<HTMLButtonElement>(`[data-iso="${cursor.value}"]`)?.focus()
}

/**
 * In a right-to-left layout the grid is mirrored, so the arrow key that moves
 * towards the start of the week is the *right* one. Reading the direction and
 * flipping the step is the whole of it - the alternative, leaving the arrows
 * alone, means the cursor visibly jumps the wrong way.
 */
function horizontal(step: number): number {
  return preferences.direction === 'rtl' ? -step : step
}

function onKeydown(event: KeyboardEvent): void {
  const handlers: Record<string, () => void> = {
    ArrowLeft: () => move(horizontal(-1)),
    ArrowRight: () => move(horizontal(1)),
    ArrowUp: () => move(-7),
    ArrowDown: () => move(7),
    PageUp: () => page(-1),
    PageDown: () => page(1),
    Home: () => {
      cursor.value = grid6.value.firstDay
      void nextTick(() => focusCursor())
    },
    Escape: () => {
      close()
      root.value?.querySelector<HTMLButtonElement>('.trigger')?.focus()
    },
  }

  const handler = handlers[event.key]

  if (handler) {
    event.preventDefault()
    handler()
  }
}

function onPointerDown(event: MouseEvent): void {
  if (open.value && root.value && !root.value.contains(event.target as Node)) close()
}

document.addEventListener('mousedown', onPointerDown)
onBeforeUnmount(() => document.removeEventListener('mousedown', onPointerDown))

// Someone switching calendar while the picker is open should see the same day
// re-laid-out, not a stale Gregorian grid.
watch(calendar, () => {
  cursor.value = props.modelValue
})

watch(
  () => props.modelValue,
  (value) => {
    cursor.value = value
  },
)
</script>

<template>
  <div ref="root" class="date-field">
    <label v-if="label" :for="id" class="field-label">{{ label }}</label>

    <button
      :id="id"
      type="button"
      class="secondary trigger"
      :aria-expanded="open"
      aria-haspopup="dialog"
      @click="toggle"
    >
      <span aria-hidden="true" class="icon">🗓</span>
      <span>{{ displayValue }}</span>
    </button>

    <div
      v-if="open"
      ref="popover"
      class="popover"
      :class="{ 'popover-end': alignToEnd }"
      role="dialog"
      :aria-label="t('datePicker.choose')"
    >
      <div class="popover-head">
        <button
          type="button"
          class="ghost step"
          :aria-label="t('datePicker.previousMonth')"
          @click="page(-1)"
        >
          <!-- The chevrons are mirrored by the layout, so they are written as
               "start" and "end" rather than as left and right. -->
          <span class="chevron chevron-start" aria-hidden="true"></span>
        </button>

        <strong class="month-label" aria-live="polite">{{ monthLabel }}</strong>

        <button
          type="button"
          class="ghost step"
          :aria-label="t('datePicker.nextMonth')"
          @click="page(1)"
        >
          <span class="chevron chevron-end" aria-hidden="true"></span>
        </button>
      </div>

      <div class="weekdays" aria-hidden="true">
        <span v-for="(heading, index) in headings" :key="index">{{ heading }}</span>
      </div>

      <div ref="grid" class="grid-days" role="grid" @keydown="onKeydown">
        <template v-for="(week, weekIndex) in grid6.weeks" :key="weekIndex">
          <button
            v-for="day in week"
            :key="day.iso"
            type="button"
            role="gridcell"
            class="day"
            :class="{
              'day-outside': !day.inMonth,
              'day-selected': day.iso === modelValue,
              'day-today': day.iso === todayIso(),
            }"
            :data-iso="day.iso"
            :disabled="isDisabled(day.iso)"
            :aria-selected="day.iso === modelValue"
            :aria-label="formatDate(day.iso, locale, calendar)"
            :tabindex="day.iso === cursor ? 0 : -1"
            @click="select(day.iso)"
          >
            {{ dayNumber(day.iso) }}
          </button>
        </template>
      </div>

      <div class="popover-foot">
        <button type="button" class="ghost" @click="select(todayIso())">
          {{ t('datePicker.today') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.date-field { position: relative; display: inline-flex; flex-direction: column; gap: var(--spacing-3xs); }

.field-label {
  margin: 0;
  font-size: var(--fontsize-body-small);
  font-weight: var(--type-font-weight-semi-bold);
}

.trigger {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-2xs);
  white-space: nowrap;
}

.icon { font-size: 15px; line-height: 1; }

.popover {
  position: absolute;
  top: calc(100% + 6px);
  /* Logical, so the popover hangs off the same edge as its trigger in both
     writing directions - and flipped by .popover-end when that edge is the one
     running out of screen. */
  inset-inline-start: 0;
  z-index: 30;
  min-width: 268px;
  padding: var(--spacing-small);
  background: var(--surface-primary);
  border: var(--border-width-small) solid var(--border-primary);
  border-radius: var(--border-radius-medium);
  box-shadow: 0 10px 30px rgb(0 0 0 / 18%);
}

.popover-end {
  inset-inline-start: auto;
  inset-inline-end: 0;
}

.popover-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-2xs);
  margin-bottom: var(--spacing-2xs);
}

.month-label { font-size: var(--fontsize-body-medium); }

.step { padding: var(--spacing-3xs) var(--spacing-2xs); line-height: 1; }

/*
 * Drawn rather than typed, so it takes its colour from the button and needs no
 * font that has the glyph.
 *
 * Two physical borders and an explicit rotation per direction. The tempting
 * version - a logical `border-inline-start` that flips on its own - only gets
 * half the job done: it moves which corner of the square is drawn, but the
 * rotation that turns that corner into an arrow stays put, and the result
 * points at the ceiling. Both halves have to flip together.
 */
.chevron {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-top: 2px solid currentColor;
  border-left: 2px solid currentColor;
}

/* "Start" and "end" of the week the user is reading, not of the screen. */
.chevron-start { transform: rotate(-45deg); }
.chevron-end { transform: rotate(135deg); }

[dir='rtl'] .chevron-start { transform: rotate(135deg); }
[dir='rtl'] .chevron-end { transform: rotate(-45deg); }

.weekdays, .grid-days {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 2px;
}

.weekdays {
  margin-bottom: 2px;
  font-size: var(--fontsize-body-small);
  color: var(--text-body);
  text-align: center;
}

.day {
  padding: var(--spacing-3xs) 0;
  border-radius: var(--border-radius-small);
  border: var(--border-width-small) solid transparent;
  background: transparent;
  color: var(--text-headings);
  font-variant-numeric: tabular-nums;
  font-weight: var(--type-font-weight-regular, 400);
}

.day:hover:not(:disabled) { background: var(--surface-subtle); }
.day:disabled { opacity: var(--opacity-disabled); cursor: not-allowed; }

/* Days spilling in from the neighbouring month are kept in the grid rather than
   blanked: a hole is a dead end for the keyboard, and paging by arrow key
   depends on being able to step into them. */
.day-outside { color: var(--text-disabled); }

/* Today is marked by an outline and the selection by a fill, so the two are
   still distinguishable when they land on the same cell. */
.day-today { border-color: var(--border-action); }

.day-selected {
  background: var(--surface-action);
  color: var(--text-on-action);
  font-weight: var(--type-font-weight-semi-bold);
}

.popover-foot {
  display: flex;
  justify-content: flex-end;
  margin-top: var(--spacing-2xs);
  padding-top: var(--spacing-2xs);
  border-top: var(--border-width-small) solid var(--border-primary);
}
</style>
