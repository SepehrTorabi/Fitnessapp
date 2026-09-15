<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api } from '@/api/client'
import { useApiMessage } from '@/composables/useApiMessage'
import { useAuthStore } from '@/stores/auth'
import { usePreferencesStore } from '@/stores/preferences'
import { formatDate, todayIso } from '@/calendar'
import { useNumbers } from '@/composables/useNumbers'
import type { DashboardData, DayView } from '@/api/types'
import WeeklyChart from '@/components/WeeklyChart.vue'
import MacroBars from '@/components/MacroBars.vue'
import DateField from '@/components/DateField.vue'
import DiaryEntryTable from '@/components/DiaryEntryTable.vue'
import { DsAlert, DsButton, DsCard, DsStatCard } from '@/design-system/components'

/**
 * The dashboard reads one week and one day, and the two are not the same thing.
 *
 *  - `anchor` is the last day of the week the chart draws. The date filter sets
 *    it, and the chart shows that day and the six before it. A single date
 *    rather than a week range on purpose: a week range would be a second
 *    selection competing with the one below, and "the seven days up to here" is
 *    a question people actually ask, while "calendar week 38" mostly is not.
 *
 *  - `selected` is the day every other card on the page describes. It follows
 *    the anchor when the week moves, and it moves on its own when a bar in the
 *    chart is clicked. That is what turns the chart from a picture into a way
 *    around the week: click Tuesday, read Tuesday, correct Tuesday.
 *
 * They are fetched separately because they answer separately. The week comes
 * from /api/dashboard and the day from /api/diary - the same endpoint the diary
 * page uses, which is what lets the entries be edited here without a second
 * implementation of anything.
 */
const auth = useAuthStore()
const preferences = usePreferencesStore()
const { t, locale } = useI18n()
const apiMessage = useApiMessage()
const { n } = useNumbers()

const today = todayIso()

const anchor = ref(today)
const selected = ref(today)

const data = ref<DashboardData | null>(null)
const day = ref<DayView | null>(null)

const error = ref('')
const dayError = ref('')
const loading = ref(true)
const dayLoading = ref(false)

const summary = computed(() => day.value?.summary ?? null)
const isToday = computed(() => selected.value === today)

/** The selected day, written out in the user's language and calendar. */
const selectedLabel = computed(() =>
  formatDate(selected.value, locale.value, preferences.calendar),
)

/** Never below zero: "you have -300 kcal left" reads worse than "300 over". */
const remaining = computed(() => {
  const value = summary.value?.remainingKcal

  return value === null || value === undefined ? null : Math.round(value)
})

const isOver = computed(() => remaining.value !== null && remaining.value < 0)

async function loadWeek(): Promise<void> {
  loading.value = true
  error.value = ''

  try {
    data.value = await api.dashboard(anchor.value, 7)
  } catch (e) {
    error.value = apiMessage(e, 'dashboard.loadFailed')
  } finally {
    loading.value = false
  }
}

async function loadDay(): Promise<void> {
  dayLoading.value = true
  dayError.value = ''

  try {
    day.value = await api.day(selected.value)
  } catch (e) {
    dayError.value = apiMessage(e, 'dashboard.dayLoadFailed')
  } finally {
    dayLoading.value = false
  }
}

/**
 * Correcting an entry changes both the day and the week - the bar for that day
 * is now a different height - so both are reloaded rather than patched locally.
 * Two requests on an edit is a fair price for never showing a total that
 * disagrees with the rows it is a total of.
 */
async function reload(): Promise<void> {
  await Promise.all([loadWeek(), loadDay()])
}

function backToToday(): void {
  anchor.value = today
  selected.value = today
}

// Moving the week moves the day with it: the newly chosen date is almost always
// the one the user wants to look at, and leaving the cards on a day that is no
// longer in the chart would be confusing.
watch(anchor, async () => {
  selected.value = anchor.value
  await loadWeek()
})

watch(selected, loadDay)

onMounted(async () => {
  await reload()
})
</script>

<template>
  <div class="page">
    <div class="head row-between">
      <h1>{{ t('dashboard.greeting', { name: auth.user?.displayName ?? '' }) }}</h1>

      <div class="filter">
        <DateField
          id="dashboard-week"
          v-model="anchor"
          :max="today"
          :label="t('dashboard.weekEnding')"
        />
      </div>
    </div>

    <!-- Without body data there is no target, and the whole dashboard is empty
         numbers. Say what is missing instead of showing zeroes. -->
    <DsAlert
      v-if="auth.needsProfile || auth.needsWeight"
      status="info"
      :title="t('dashboard.onboardingTitle')"
      class="onboarding"
    >
      {{ auth.needsProfile ? t('dashboard.onboardingProfile') : t('dashboard.onboardingWeight') }}

      <RouterLink to="/profile" class="onboarding-action">
        <DsButton variant="outline" size="sm" href="/profile">
          {{ t('dashboard.onboardingButton') }}
        </DsButton>
      </RouterLink>
    </DsAlert>

    <p v-if="loading" class="muted">{{ t('common.loading') }}</p>
    <DsAlert v-else-if="error" status="error">{{ error }}</DsAlert>

    <template v-else-if="data">
      <!-- The week's chart gets the full width: seven bars and their budget
           markers need the room to stay readable. -->
      <DsCard class="block">
        <WeeklyChart :days="data.history" :selected="selected" @select="selected = $event" />

        <!-- A plain link, not a fetch-and-blob: the endpoint is same-origin, so
             the session cookie rides along, and letting the browser handle the
             download means it honours the filename the server sends and never
             holds the file in memory. -->
        <div class="export row-between">
          <p class="muted small export-hint">{{ t('dashboard.exportHint') }}</p>
          <DsButton variant="outline" size="sm" href="/api/me/export/diary.pdf">
            {{ t('dashboard.exportPdf') }}
          </DsButton>
        </div>
      </DsCard>

      <!-- Everything below describes the selected day, so it says which day that
           is, once, rather than repeating the date in every card heading. -->
      <div class="day-head row-between">
        <p class="viewing">
          <strong>{{ selectedLabel }}</strong>
          <span v-if="isToday" class="muted small today-note">· {{ t('common.today') }}</span>
        </p>

        <button v-if="!isToday" class="secondary small-btn" type="button" @click="backToToday">
          {{ t('dashboard.backToToday') }}
        </button>
      </div>

      <DsAlert v-if="dayError" status="error" class="block">{{ dayError }}</DsAlert>

      <template v-else-if="summary">
        <div class="grid grid-3 stats">
          <DsStatCard
            :label="isToday ? t('dashboard.eatenToday') : t('dashboard.eatenOnDay')"
            :value="`${n(summary.consumed.kcal)} ${t('common.kcal')}`"
          />
          <DsStatCard
            :label="t('dashboard.burned')"
            :value="`${n(summary.caloriesBurned)} ${t('common.kcal')}`"
          />
          <DsStatCard
            :label="
              isOver
                ? t('dashboard.overBudget')
                : isToday
                  ? t('dashboard.leftToday')
                  : t('dashboard.leftOnDay')
            "
            :value="
              remaining === null ? t('common.none') : `${n(Math.abs(remaining))} ${t('common.kcal')}`
            "
            :trend="remaining === null ? undefined : isOver ? 'down' : 'up'"
            :trend-label="
              summary.target
                ? t('dashboard.budget', { kcal: n(summary.budgetKcal ?? 0) })
                : undefined
            "
          />
        </div>

        <DsCard class="block">
          <h3>{{ isToday ? t('dashboard.macrosToday') : t('dashboard.macrosOnDay') }}</h3>
          <MacroBars :consumed="summary.consumed" :target="summary.target?.macros ?? null" />

          <p v-if="summary.target" class="small muted target-detail">
            {{
              t('dashboard.targetExplain', {
                target: n(summary.target.targetKcal),
                bmr: n(summary.target.bmr),
                tdee: n(summary.target.tdee),
                formula: t(`formula.${summary.target.formula}`),
              })
            }}
          </p>
        </DsCard>

        <DsCard class="block">
          <div class="row-between">
            <h3>{{ isToday ? t('dashboard.todaysEntries') : t('dashboard.entriesOnDay') }}</h3>
            <RouterLink to="/diary" class="small">{{ t('dashboard.addSomething') }}</RouterLink>
          </div>

          <p v-if="dayLoading" class="muted">{{ t('common.loading') }}</p>

          <p v-else-if="!day || day.entries.length === 0" class="empty">
            {{ isToday ? t('dashboard.nothingToday') : t('dashboard.nothingOnDay') }}
          </p>

          <DiaryEntryTable v-else :entries="day.entries" @changed="reload" />
        </DsCard>

        <DsCard v-if="day && day.activities.length" class="block">
          <h3>{{ t('dashboard.activityOnDay') }}</h3>

          <table>
            <tbody>
              <tr v-for="activity in day.activities" :key="activity.id">
                <td>
                  {{ activity.description }}
                  <span v-if="activity.durationMinutes" class="muted small">
                    · {{ n(activity.durationMinutes) }} {{ t('diary.minutesShort') }}
                  </span>
                </td>
                <td class="num">
                  {{ n(activity.caloriesBurned) }} {{ t('common.kcal') }}
                </td>
              </tr>
            </tbody>
          </table>
        </DsCard>
      </template>

      <p v-if="data.averages.daysLogged > 0" class="muted small averages">
        {{
          t(
            'dashboard.averages',
            {
              count: data.averages.daysLogged,
              kcal: n(data.averages.kcal),
              protein: n(data.averages.proteinG),
            },
            data.averages.daysLogged,
          )
        }}
      </p>
    </template>
  </div>
</template>

<style scoped>
.head { align-items: flex-end; margin-bottom: var(--spacing-medium); }
.head h1 { margin: 0; }
.filter { flex: none; }

.onboarding { margin-bottom: var(--spacing-medium); }
.onboarding-action { display: inline-block; margin-top: var(--spacing-small); }
.onboarding-action:hover { text-decoration: none; }

.stats { margin-bottom: var(--spacing-medium); }
.block { margin-bottom: var(--spacing-medium); }

/* The line that says which day the cards below belong to. Given a rule above it
   so the page reads as two sections - the week, then one day of it. */
.day-head {
  margin-bottom: var(--spacing-small);
  padding-top: var(--spacing-small);
  border-top: var(--border-width-small) solid var(--border-primary);
}

.viewing { margin: 0; font-size: var(--fontsize-body-large); }
.today-note { margin-inline-start: var(--spacing-3xs); }
.small-btn { padding: 5px 11px; font-size: 13px; }

.target-detail {
  margin: var(--spacing-medium) 0 0;
  padding-top: var(--spacing-small);
  border-top: var(--border-width-small) solid var(--border-primary);
}

.export {
  margin-top: var(--spacing-medium);
  padding-top: var(--spacing-small);
  border-top: var(--border-width-small) solid var(--border-primary);
}

.export-hint { margin: 0; }
.averages { margin-top: var(--spacing-medium); }
</style>
