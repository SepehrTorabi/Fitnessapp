<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useNumbers } from '@/composables/useNumbers'
import type { Exercise } from '@/api/types'

/**
 * A type-to-filter picker for the exercise catalogue.
 *
 * A plain <select> was fine with fifteen seeded exercises and stops being fine
 * the moment a gym's worth of them exists - scrolling a list of two hundred to
 * find "rowing" is not a thing anyone should have to do. Typing narrows it.
 *
 * Built as a combobox rather than a search box that filters a list below,
 * because the field has to end up holding exactly one exercise: what is typed
 * is a filter, not a value. The distinction matters for what happens on blur -
 * an abandoned half-typed word reverts to whatever was actually chosen, rather
 * than leaving the field looking like it holds something it does not.
 *
 * Follows the ARIA combobox pattern: the input owns the listbox, the active
 * option is pointed at by aria-activedescendant rather than by moving focus, so
 * the input keeps it and typing keeps working.
 */
const props = defineProps<{
  exercises: Exercise[]
  modelValue: number | null
  id?: string
}>()

const emit = defineEmits<{ 'update:modelValue': [value: number | null] }>()

const { t } = useI18n()
const { decimal } = useNumbers()

const root = ref<HTMLElement | null>(null)
const input = ref<HTMLInputElement | null>(null)
const list = ref<HTMLElement | null>(null)

const open = ref(false)
const term = ref('')
/** Index into `matches`, or -1 when nothing is highlighted yet. */
const active = ref(-1)

const selected = computed(
  () => props.exercises.find((exercise) => exercise.id === props.modelValue) ?? null,
)

/**
 * Case- and accent-insensitive, and matching anywhere in the name rather than
 * only at the start: somebody looking for the rowing machine types "row", and
 * somebody looking for "Running, 12 km/h" may well type "12".
 */
function normalise(value: string): string {
  return value
    .toLocaleLowerCase()
    .normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
}

const matches = computed(() => {
  const needle = normalise(term.value.trim())

  if (needle === '') return props.exercises

  return props.exercises.filter((exercise) => normalise(exercise.name).includes(needle))
})

/** What the field shows when it is not being typed in. */
function displayValue(): string {
  return selected.value?.name ?? ''
}

function show(): void {
  open.value = true
  // Start the highlight on what is already chosen, so Enter on an untouched
  // field is a no-op rather than a surprise.
  active.value = matches.value.findIndex((exercise) => exercise.id === props.modelValue)
}

function close(revert = true): void {
  open.value = false
  active.value = -1

  // The typed text was a filter, not a value. Whatever is actually selected is
  // what the field should read.
  if (revert) term.value = displayValue()
}

function choose(exercise: Exercise): void {
  emit('update:modelValue', exercise.id)
  term.value = exercise.name
  close(false)
  input.value?.focus()
}

function onInput(): void {
  open.value = true
  // A new filter invalidates the old highlight; point at the first match so
  // Enter picks the obvious one.
  active.value = matches.value.length > 0 ? 0 : -1
}

function move(delta: number): void {
  if (!open.value) {
    show()

    return
  }

  const count = matches.value.length
  if (count === 0) return

  // Wraps, so Up from the top reaches the bottom of a long list in one press.
  active.value = (active.value + delta + count) % count
  scrollActiveIntoView()
}

function scrollActiveIntoView(): void {
  void nextTick(() => {
    list.value
      ?.querySelector<HTMLElement>('[data-active="true"]')
      ?.scrollIntoView({ block: 'nearest' })
  })
}

function onKeydown(event: KeyboardEvent): void {
  switch (event.key) {
    case 'ArrowDown':
      event.preventDefault()
      move(1)
      break
    case 'ArrowUp':
      event.preventDefault()
      move(-1)
      break
    case 'Enter': {
      const match = matches.value[active.value]

      // Only swallow Enter when it is actually picking something; otherwise let
      // it submit the form the field sits in.
      if (open.value && match) {
        event.preventDefault()
        choose(match)
      }

      break
    }
    case 'Escape':
      if (open.value) {
        event.preventDefault()
        close()
      }

      break
    default:
      break
  }
}

function onPointerDown(event: MouseEvent): void {
  if (open.value && root.value && !root.value.contains(event.target as Node)) close()
}

document.addEventListener('mousedown', onPointerDown)
onBeforeUnmount(() => document.removeEventListener('mousedown', onPointerDown))

// Keep the field in step when the value or the catalogue changes from outside -
// picking a suggestion, or a trainer renaming something.
watch(
  () => [props.modelValue, props.exercises] as const,
  () => {
    if (!open.value) term.value = displayValue()
  },
  { immediate: true },
)
</script>

<template>
  <div ref="root" class="picker">
    <input
      :id="id"
      ref="input"
      v-model="term"
      type="text"
      role="combobox"
      autocomplete="off"
      aria-autocomplete="list"
      :aria-expanded="open"
      :aria-controls="`${id ?? 'picker'}-list`"
      :aria-activedescendant="
        open && active >= 0 ? `${id ?? 'picker'}-option-${matches[active]?.id}` : undefined
      "
      :placeholder="t('activity.choosePlaceholder')"
      @focus="show"
      @input="onInput"
      @keydown="onKeydown"
    />

    <ul
      v-if="open"
      :id="`${id ?? 'picker'}-list`"
      ref="list"
      class="options"
      role="listbox"
    >
      <li v-if="matches.length === 0" class="no-match muted small">
        {{ t('activity.noMatchingExercise') }}
      </li>

      <li
        v-for="(exercise, index) in matches"
        :id="`${id ?? 'picker'}-option-${exercise.id}`"
        :key="exercise.id"
        role="option"
        class="option"
        :class="{ 'option-active': index === active, 'option-chosen': exercise.id === modelValue }"
        :data-active="index === active"
        :aria-selected="exercise.id === modelValue"
        @mouseenter="active = index"
        @mousedown.prevent="choose(exercise)"
      >
        <span class="option-name">{{ exercise.name }}</span>
        <span class="muted small">
          {{ t('activity.perMinute', { kcal: decimal(exercise.kcalPerMinute) }) }}
        </span>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.picker { position: relative; }

.options {
  position: absolute;
  top: calc(100% + 4px);
  inset-inline: 0;
  z-index: 30;
  max-height: 260px;
  overflow-y: auto;
  margin: 0;
  padding: var(--spacing-3xs);
  list-style: none;
  background: var(--surface-primary);
  border: var(--border-width-small) solid var(--border-primary);
  border-radius: var(--border-radius-small);
  box-shadow: 0 10px 30px rgb(0 0 0 / 18%);
}

.option {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--spacing-small);
  padding: var(--spacing-3xs) var(--spacing-2xs);
  border-radius: var(--border-radius-small);
  cursor: pointer;
}

/* The keyboard's position and the current value are different things and are
   marked differently: a tint follows the arrow keys, a weight marks what is
   actually chosen. */
.option-active { background: var(--surface-subtle); }
.option-chosen .option-name { font-weight: var(--type-font-weight-semi-bold); }

.option-name { color: var(--text-headings); }
.no-match { padding: var(--spacing-2xs); }
</style>
