<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '@/api/client'
import { useApiMessage } from '@/composables/useApiMessage'
import { useNumbers } from '@/composables/useNumbers'
import { useAuthStore } from '@/stores/auth'
import { todayIso } from '@/calendar'
import type { ActivityDay, Exercise, ExerciseSuggestions } from '@/api/types'
import DateField from '@/components/DateField.vue'
import ExerciseCatalogue from '@/components/ExerciseCatalogue.vue'
import ExercisePicker from '@/components/ExercisePicker.vue'
import VideoPlayer from '@/components/VideoPlayer.vue'
import { DsAlert, DsBadge, DsCard, DsStatCard } from '@/design-system/components'
import ActivityTable from '@/components/ActivityTable.vue'

/**
 * Everything to do with training, on one page.
 *
 * It exists because activity used to be a form at the bottom of the food page,
 * where it was only ever found by somebody already logging a meal. Eating and
 * training happen at different moments and deserve different places.
 *
 * What a given person sees depends on what they may do:
 *
 *  - everyone logs sessions and reads the suggestions their goal produces;
 *  - a trainer additionally gets the catalogue, with the editing in it.
 *
 * The role only decides what is rendered. Every restricted call is checked
 * again by the API, so this is about not offering a button that cannot work.
 */
const { t, locale } = useI18n()
const auth = useAuthStore()
const apiMessage = useApiMessage()
const { n, decimal } = useNumbers()

const today = todayIso()
const date = ref(today)

const day = ref<ActivityDay | null>(null)
const suggestions = ref<ExerciseSuggestions | null>(null)
const exercises = ref<Exercise[]>([])

const loading = ref(true)
const error = ref('')
const formError = ref('')
const saving = ref(false)

/**
 * Which of the two ways in is on screen.
 *
 * The catalogue first, because it is the one that does arithmetic for you - but
 * free text is one click away and never behind a menu, because a catalogue can
 * never cover an afternoon spent helping somebody move.
 */
const mode = ref<'catalogue' | 'free'>('catalogue')

// --- Logging from the catalogue ---
const exerciseId = ref<number | null>(null)
const minutes = ref<number | null>(30)
/** Null means "use the rate". A number means the user disagreed with it. */
const overrideKcal = ref<number | null>(null)

// --- Logging in the user's own words ---
const description = ref('')
const freeKcal = ref<number | null>(null)
const freeMinutes = ref<number | null>(null)

const chosen = computed(() => exercises.value.find((e) => e.id === exerciseId.value) ?? null)

/**
 * The clip currently open in the player, or null when it is closed.
 *
 * Held as the whole exercise rather than a URL so the dialog can title itself,
 * and so that closing and reopening does not need a second lookup.
 */
const playing = ref<Exercise | null>(null)

/** What the chosen exercise and duration come to, before any override. */
const estimated = computed(() => {
  if (!chosen.value || !minutes.value || minutes.value <= 0) return null

  return chosen.value.kcalPerMinute * minutes.value
})

const goalLabel = computed(() =>
  suggestions.value?.goal ? t(`goal.${suggestions.value.goal}`) : null,
)

async function loadDay(): Promise<void> {
  loading.value = true
  error.value = ''

  try {
    day.value = await api.activityDay(date.value)
  } catch (e) {
    error.value = apiMessage(e, 'activity.loadFailed')
  } finally {
    loading.value = false
  }
}

async function loadCatalogue(): Promise<void> {
  try {
    exercises.value = (await api.exercises()).exercises
  } catch (e) {
    error.value = apiMessage(e, 'activity.catalogueLoadFailed')
  }
}

async function loadSuggestions(): Promise<void> {
  try {
    suggestions.value = await api.activitySuggestions()
  } catch {
    // Suggestions are a nicety on a page whose real job is logging. Failing to
    // load them must not stop somebody recording their run.
    suggestions.value = null
  }
}

async function logFromCatalogue(): Promise<void> {
  if (!exerciseId.value || !minutes.value) return

  saving.value = true
  formError.value = ''

  try {
    await api.addActivityFromExercise({
      exerciseId: exerciseId.value,
      durationMinutes: minutes.value,
      caloriesBurned: overrideKcal.value,
      performedOn: date.value,
    })

    overrideKcal.value = null
    await loadDay()
  } catch (e) {
    formError.value = apiMessage(e, 'activity.saveFailed')
  } finally {
    saving.value = false
  }
}

async function logFreeText(): Promise<void> {
  if (!description.value.trim() || freeKcal.value === null) {
    formError.value = t('activity.incomplete')

    return
  }

  saving.value = true
  formError.value = ''

  try {
    await api.addActivity({
      description: description.value.trim(),
      caloriesBurned: freeKcal.value,
      durationMinutes: freeMinutes.value,
      performedOn: date.value,
    })

    description.value = ''
    freeKcal.value = null
    freeMinutes.value = null
    await loadDay()
  } catch (e) {
    formError.value = apiMessage(e, 'activity.saveFailed')
  } finally {
    saving.value = false
  }
}

/** Pick a suggested exercise up into the form rather than logging it blind. */
function useSuggestion(exercise: Exercise): void {
  mode.value = 'catalogue'
  exerciseId.value = exercise.id
  overrideKcal.value = null

  document.getElementById('activity-form')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

async function onCatalogueChanged(): Promise<void> {
  // A rate may have moved, so the suggestions and the picker both need rereading.
  await Promise.all([loadCatalogue(), loadSuggestions()])
}

watch(date, loadDay)

// Changing the language re-renders the labels; the suggestions themselves are
// keyed off the goal, which has not moved, so only the day needs no reload.
watch(locale, loadSuggestions)

onMounted(async () => {
  await Promise.all([loadDay(), loadCatalogue(), loadSuggestions()])
})
</script>

<template>
  <div class="page">
    <div class="head row-between">
      <div>
        <h1>{{ t('activity.title') }}</h1>
        <p class="muted intro">{{ t('activity.intro') }}</p>
      </div>

      <DateField id="activity-date" v-model="date" :max="today" :label="t('common.day')" />
    </div>

    <DsAlert v-if="error" status="error" class="block">{{ error }}</DsAlert>

    <div class="grid grid-2">
      <!-- ---------- Logging ---------- -->
      <div class="stack">
        <DsCard id="activity-form">
          <h3>{{ t('activity.logTitle') }}</h3>

          <!-- Two ways in, as a tab strip rather than a dropdown: both are
               one click away and the choice is visible without opening it. -->
          <div class="modes" role="tablist">
            <button
              type="button"
              role="tab"
              :aria-selected="mode === 'catalogue'"
              class="mode"
              :class="{ 'mode-active': mode === 'catalogue' }"
              @click="mode = 'catalogue'"
            >
              {{ t('activity.fromCatalogue') }}
            </button>
            <button
              type="button"
              role="tab"
              :aria-selected="mode === 'free'"
              class="mode"
              :class="{ 'mode-active': mode === 'free' }"
              @click="mode = 'free'"
            >
              {{ t('activity.ownWords') }}
            </button>
          </div>

          <p v-if="formError" class="alert alert-error small note">{{ formError }}</p>

          <!-- ----- From the catalogue ----- -->
          <form v-if="mode === 'catalogue'" class="stack" @submit.prevent="logFromCatalogue">
            <div>
              <label for="exercise">{{ t('activity.chooseExercise') }}</label>
              <!-- Type to narrow it: a plain dropdown stops working once a gym's
                   worth of exercises exists. -->
              <ExercisePicker id="exercise" v-model="exerciseId" :exercises="exercises" />
            </div>

            <div class="row">
              <div class="field">
                <label for="minutes">{{ t('activity.minutes') }}</label>
                <input id="minutes" v-model.number="minutes" type="number" min="1" max="1440" />
              </div>

              <div class="field">
                <label for="override">{{ t('activity.overrideCalories') }}</label>
                <!-- Pre-filled with the estimate and freely editable: the rate
                     is an average, and the person who did the session knows
                     better than the average does. -->
                <input
                  id="override"
                  v-model.number="overrideKcal"
                  type="number"
                  min="0"
                  :placeholder="estimated === null ? '' : String(Math.round(estimated))"
                />
              </div>
            </div>

            <p v-if="chosen && estimated !== null" class="estimate">
              <strong>{{ n(overrideKcal ?? estimated) }} {{ t('common.kcal') }}</strong>
              <span class="muted small">
                {{
                  t('activity.estimateHint', {
                    rate: `${decimal(chosen.kcalPerMinute)} ${t('common.kcal')}`,
                  })
                }}
              </span>
            </p>

            <button type="submit" :disabled="saving || !exerciseId || !minutes">
              {{ saving ? t('activity.adding') : t('activity.add') }}
            </button>
          </form>

          <!-- ----- In the user's own words ----- -->
          <form v-else class="stack" @submit.prevent="logFreeText">
            <div>
              <label for="description">{{ t('activity.describeIt') }}</label>
              <input
                id="description"
                v-model="description"
                type="text"
                :placeholder="t('activity.describePlaceholder')"
              />
            </div>

            <div class="row">
              <div class="field">
                <label for="free-kcal">{{ t('activity.caloriesLabel') }}</label>
                <input id="free-kcal" v-model.number="freeKcal" type="number" min="0" />
              </div>
              <div class="field">
                <label for="free-minutes">{{ t('activity.minutesOptional') }}</label>
                <input id="free-minutes" v-model.number="freeMinutes" type="number" min="1" />
              </div>
            </div>

            <button type="submit" :disabled="saving">
              {{ saving ? t('activity.adding') : t('activity.add') }}
            </button>
          </form>
        </DsCard>

        <!-- ---------- Suggestions ---------- -->
        <section class="section">
          <h3>{{ t('activity.suggestionsTitle') }}</h3>

          <p v-if="goalLabel" class="muted small note">
            {{ t('activity.suggestionsFor', { goal: goalLabel }) }}
          </p>

          <p v-if="!suggestions || suggestions.goal === null" class="empty">
            {{ t('activity.suggestionsNoGoal') }}
          </p>

          <p v-else-if="suggestions.exercises.length === 0" class="empty">
            {{ t('activity.suggestionsEmpty') }}
          </p>

          <ul v-else class="suggestions">
            <li v-for="exercise in suggestions.exercises" :key="exercise.id" class="suggestion-row">
              <button class="suggestion" type="button" @click="useSuggestion(exercise)">
                <span class="suggestion-main">
                  <span class="suggestion-name">{{ exercise.name }}</span>
                  <span class="muted small">
                    {{ t('activity.perMinute', { kcal: decimal(exercise.kcalPerMinute) }) }}
                  </span>
                </span>
                <span class="badges">
                  <DsBadge
                    v-for="purpose in exercise.purposes"
                    :key="purpose"
                    :label="t(`purpose.${purpose}`)"
                  />
                </span>
              </button>

              <!-- Its own button rather than part of the row above, because it
                   does something different: the row picks the exercise up into
                   the form, this one only shows you how it is done. Nesting a
                   button inside a button is also invalid HTML.

                   Always visible, never hover-only. A hover affordance does not
                   exist on a touch screen and is invisible to a keyboard, and
                   this is the one control on the page somebody unsure of the
                   movement most needs to find. -->
              <button
                v-if="exercise.videoUrl"
                type="button"
                class="how-to"
                @click="playing = exercise"
              >
                <span class="play" aria-hidden="true"></span>
                <span>{{ t('activity.howTo') }}</span>
              </button>
            </li>
          </ul>
        </section>
      </div>

      <!-- ---------- The day ---------- -->
      <div class="stack">
        <DsStatCard
          :label="t('activity.burnedTotal')"
          :value="`${n(day?.caloriesBurned ?? 0)} ${t('common.kcal')}`"
        />

        <section class="section">
          <h3>{{ t('activity.dayTitle') }}</h3>

          <p v-if="loading" class="muted">{{ t('common.loading') }}</p>

          <p v-else-if="!day || day.activities.length === 0" class="empty">
            {{ t('activity.nothingYet') }}
          </p>

          <ActivityTable v-else :activities="day.activities" @changed="loadDay" />
        </section>
      </div>
    </div>

    <VideoPlayer
      :open="playing !== null"
      :src="playing?.videoUrl ?? ''"
      :mime-type="playing?.videoMimeType"
      :title="playing?.name ?? ''"
      @close="playing = null"
    />

    <!-- ---------- Trainers only ---------- -->
    <div v-if="auth.canManageExercises" class="catalogue-block">
      <ExerciseCatalogue :exercises="exercises" @changed="onCatalogueChanged" />
    </div>
  </div>
</template>

<style scoped>
.head { align-items: flex-end; margin-bottom: var(--spacing-medium); }
.head h1 { margin: 0; }
.intro { margin: var(--spacing-3xs) 0 0; max-width: 60ch; }

.block { margin-bottom: var(--spacing-medium); }
.note { margin: 0 0 var(--spacing-small); }
.field { flex: 1; min-width: 120px; }

.modes {
  display: flex;
  gap: var(--spacing-3xs);
  margin: var(--spacing-small) 0 var(--spacing-medium);
}

.mode {
  flex: 1;
  padding: var(--spacing-2xs) var(--spacing-small);
  background: var(--surface-subtle);
  color: var(--text-body);
  border: var(--border-width-small) solid transparent;
  border-radius: var(--border-radius-small);
  font-size: var(--fontsize-body-small);
}

.mode-active {
  background: var(--surface-primary);
  border-color: var(--border-action);
  color: var(--text-action);
}

.estimate {
  display: flex;
  flex-direction: column;
  gap: 2px;
  margin: 0;
  padding: var(--spacing-2xs) var(--spacing-small);
  background: var(--surface-subtle);
  border-radius: var(--border-radius-small);
}

.suggestions {
  list-style: none;
  margin: var(--spacing-small) 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;

}

/*
 * Rows separated by a hairline, not eight stacked boxes.
 *
 * Every suggestion used to carry its own fill and border, so a list of eight
 * read as eight things competing for attention - inside a panel that was itself
 * a box. None of them is emphasised over the others, so none of them needs the
 * chrome. The exercise and its "how to" link are still one thing: they share a
 * row, and the hairline falls between suggestions rather than between the two
 * halves of one.
 *
 * The hover fill stays. It is the one state here that really is about a single
 * row rather than the set.
 */
.suggestion-row {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-top: var(--border-width-small) solid var(--border-primary);
}

.suggestion-row:first-child { border-top: none; }

.suggestion-row:hover { background: var(--surface-subtle); }

/*
 * Under the exercise rather than beside it: the label is a sentence, and at
 * phone width a row of two buttons would either wrap awkwardly or squeeze the
 * name it belongs to. Full width and inside the card, with a hairline above it,
 * so it reads as the second line of this suggestion rather than a loose link.
 */
.how-to {
  display: flex;
  align-items: center;
  gap: var(--spacing-2xs);
  width: 100%;
  padding: var(--spacing-3xs) var(--spacing-small);
  text-align: start;
  background: transparent;
  border: none;
  border-top: var(--border-width-small) solid var(--border-primary);
  border-radius: 0;
  color: var(--text-action);
  font-size: var(--fontsize-body-small);
}

.how-to:hover { color: var(--text-action-hover); text-decoration: underline; }

/*
 * The triangle is drawn rather than typed.
 *
 * The ▶ character carries its own side bearings and sits noticeably left of
 * centre inside a circle, which no amount of padding fixes reliably - the
 * offset depends on whichever font actually renders it. A border triangle has
 * no bearings at all, so its position is arithmetic.
 *
 * It still needs a nudge: a triangle's visual weight is towards its flat edge,
 * so one centred on its bounding box reads as if it has slipped backwards. The
 * 1px shift is optical centring, and it is a physical margin rather than a
 * logical one because a play symbol points in the direction of time, not in the
 * direction of the script - it stays pointing right in Farsi.
 */
.play {
  display: grid;
  place-items: center;
  width: 18px;
  height: 18px;
  flex: none;
  border-radius: 50%;
  background: var(--surface-action);
}

.play::before {
  content: '';
  width: 0;
  height: 0;
  border-style: solid;
  border-width: 4px 0 4px 7px;
  border-color: transparent transparent transparent var(--text-on-action);
  margin-left: 1px;
}

.suggestion {
  width: 100%;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-2xs);
  text-align: start;
  padding: var(--spacing-2xs) var(--spacing-small);
  /* The card behind it draws the background and the border now. */
  background: transparent;
  color: var(--text-headings);
  border: none;
  border-radius: 0;
}

.suggestion-main { display: flex; flex-direction: column; gap: 1px; }
.suggestion-name { font-weight: var(--type-font-weight-semi-bold); }
.badges { display: flex; flex-wrap: wrap; gap: 4px; }

.catalogue-block { margin-top: var(--spacing-medium); }
.shrink { width: 1%; white-space: nowrap; }
.block { display: block; }
</style>
