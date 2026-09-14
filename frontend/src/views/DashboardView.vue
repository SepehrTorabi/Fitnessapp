<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { api } from '@/api/client'
import { useApiMessage } from '@/composables/useApiMessage'
import { useAuthStore } from '@/stores/auth'
import type { DashboardData } from '@/api/types'
import WeeklyChart from '@/components/WeeklyChart.vue'
import MacroBars from '@/components/MacroBars.vue'

const auth = useAuthStore()
const { t } = useI18n()
const apiMessage = useApiMessage()

const data = ref<DashboardData | null>(null)
const error = ref('')
const loading = ref(true)

const today = computed(() => data.value?.today ?? null)

/** Never below zero: "you have -300 kcal left" reads worse than "300 over". */
const remaining = computed(() => {
  const value = today.value?.remainingKcal
  return value === null || value === undefined ? null : Math.round(value)
})

onMounted(async () => {
  try {
    data.value = await api.dashboard(undefined, 7)
  } catch (e) {
    error.value = apiMessage(e, 'dashboard.loadFailed')
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="page">
    <h1>{{ t('dashboard.greeting', { name: auth.user?.displayName ?? '' }) }}</h1>

    <!-- Without body data there is no target, and the whole dashboard is empty
         numbers. Say what is missing instead of showing zeroes. -->
    <div v-if="auth.needsProfile || auth.needsWeight" class="card onboarding">
      <h2>{{ t('dashboard.onboardingTitle') }}</h2>
      <p class="muted">
        {{ auth.needsProfile ? t('dashboard.onboardingProfile') : t('dashboard.onboardingWeight') }}
      </p>
      <RouterLink to="/profile">
        <button type="button">{{ t('dashboard.onboardingButton') }}</button>
      </RouterLink>
    </div>

    <p v-if="loading" class="muted">{{ t('common.loading') }}</p>
    <p v-else-if="error" class="alert alert-error">{{ error }}</p>

    <template v-else-if="data && today">
      <div class="grid grid-3 stats">
        <div class="card">
          <p class="stat-label">{{ t('dashboard.eatenToday') }}</p>
          <p class="stat-value">
            {{ Math.round(today.consumed.kcal) }}<span class="unit">{{ t('common.kcal') }}</span>
          </p>
        </div>

        <div class="card">
          <p class="stat-label">{{ t('dashboard.burned') }}</p>
          <p class="stat-value">
            {{ Math.round(today.caloriesBurned) }}<span class="unit">{{ t('common.kcal') }}</span>
          </p>
        </div>

        <div class="card">
          <p class="stat-label">
            {{ remaining !== null && remaining < 0 ? t('dashboard.overBudget') : t('dashboard.leftToday') }}
          </p>
          <p
            class="stat-value"
            :class="remaining === null ? '' : remaining < 0 ? 'value-over' : 'value-good'"
          >
            {{ remaining === null ? t('common.none') : Math.abs(remaining)
            }}<span class="unit">{{ t('common.kcal') }}</span>
          </p>
          <p v-if="today.target" class="small muted">
            {{ t('dashboard.budget', { kcal: Math.round(today.budgetKcal ?? 0) }) }}
          </p>
        </div>
      </div>

      <!-- The week's chart gets the full width: seven bars and their budget
           markers need the room to stay readable. -->
      <div class="card main">
        <WeeklyChart :days="data.history" />

        <!-- A plain link, not a fetch-and-blob: the endpoint is same-origin, so
             the session cookie rides along, and letting the browser handle the
             download means it honours the filename the server sends and never
             holds the file in memory. -->
        <div class="export row-between">
          <p class="muted small export-hint">{{ t('dashboard.exportHint') }}</p>
          <a class="export-button" href="/api/me/export/diary.pdf">
            <span aria-hidden="true">&#8595;</span> {{ t('dashboard.exportPdf') }}
          </a>
        </div>
      </div>

      <div class="card main">
        <h3>{{ t('dashboard.macrosToday') }}</h3>
        <MacroBars :consumed="today.consumed" :target="today.target?.macros ?? null" />

        <div v-if="today.target" class="target-detail">
          <p class="small muted">
            {{
              t('dashboard.targetExplain', {
                target: today.target.targetKcal,
                bmr: today.target.bmr,
                tdee: today.target.tdee,
                formula: t(`formula.${today.target.formula}`),
              })
            }}
          </p>
        </div>
      </div>

      <div class="card">
        <div class="row-between">
          <h3>{{ t('dashboard.todaysEntries') }}</h3>
          <RouterLink to="/diary" class="small">{{ t('dashboard.addSomething') }}</RouterLink>
        </div>

        <p v-if="data.recentEntries.length === 0" class="empty">
          {{ t('dashboard.nothingToday') }}
        </p>

        <table v-else>
          <thead>
            <tr>
              <th>{{ t('dashboard.tableFood') }}</th>
              <th>{{ t('dashboard.tableMeal') }}</th>
              <th class="num">{{ t('dashboard.tableAmount') }}</th>
              <th class="num">{{ t('common.kcal') }}</th>
              <th class="num">{{ t('dashboard.tableMacros') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in data.recentEntries" :key="entry.id">
              <td>{{ entry.label }}</td>
              <td class="muted">{{ t(`meal.${entry.mealType}`) }}</td>
              <td class="num">{{ entry.quantity }} {{ entry.portionLabel ?? entry.unit }}</td>
              <td class="num">{{ Math.round(entry.nutrients.kcal) }}</td>
              <td class="num muted">
                {{ Math.round(entry.nutrients.proteinG) }} /
                {{ Math.round(entry.nutrients.carbsG) }} /
                {{ Math.round(entry.nutrients.fatG) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pluralised by vue-i18n: German and English do not split the singular
           and plural in the same place, so the whole sentence is one message
           with two forms rather than a word stitched on at runtime. -->
      <p v-if="data.averages.daysLogged > 0" class="muted small averages">
        {{
          t(
            'dashboard.averages',
            {
              count: data.averages.daysLogged,
              kcal: data.averages.kcal,
              protein: data.averages.proteinG,
            },
            data.averages.daysLogged,
          )
        }}
      </p>
    </template>
  </div>
</template>

<style scoped>
.onboarding { border-left: 3px solid var(--accent); margin-bottom: 16px; }
.stats { margin-bottom: 16px; }
.main { margin-bottom: 16px; }
.unit { font-size: 14px; font-weight: 600; color: var(--text-muted); margin-left: 5px; }
.value-good { color: var(--good); }
.value-over { color: var(--over); }
.target-detail { margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border); }
.averages { margin-top: 16px; }

.export {
  margin-top: 18px;
  padding-top: 14px;
  border-top: 1px solid var(--border);
}

.export-hint { margin: 0; }

.export-button {
  display: inline-block;
  padding: 8px 14px;
  border-radius: var(--radius-sm);
  background: var(--surface-2);
  border: 1px solid var(--border);
  color: var(--text);
  font-size: 14px;
  font-weight: 600;
}

.export-button:hover {
  border-color: var(--accent);
  color: var(--accent);
  text-decoration: none;
}
</style>
