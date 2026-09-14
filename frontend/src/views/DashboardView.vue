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
import { DsAlert, DsBadge, DsButton, DsCard, DsStatCard, DsTable } from '@/design-system/components'

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

const isOver = computed(() => remaining.value !== null && remaining.value < 0)

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
    <DsAlert v-if="auth.needsProfile || auth.needsWeight" status="info" :title="t('dashboard.onboardingTitle')" class="onboarding">
      {{ auth.needsProfile ? t('dashboard.onboardingProfile') : t('dashboard.onboardingWeight') }}

      <RouterLink to="/profile" class="onboarding-action">
        <DsButton variant="outline" size="sm" href="/profile">
          {{ t('dashboard.onboardingButton') }}
        </DsButton>
      </RouterLink>
    </DsAlert>

    <p v-if="loading" class="muted">{{ t('common.loading') }}</p>
    <DsAlert v-else-if="error" status="error">{{ error }}</DsAlert>

    <template v-else-if="data && today">
      <div class="grid grid-3 stats">
        <DsStatCard
          :label="t('dashboard.eatenToday')"
          :value="`${Math.round(today.consumed.kcal)} ${t('common.kcal')}`"
        />
        <DsStatCard
          :label="t('dashboard.burned')"
          :value="`${Math.round(today.caloriesBurned)} ${t('common.kcal')}`"
        />
        <DsStatCard
          :label="isOver ? t('dashboard.overBudget') : t('dashboard.leftToday')"
          :value="
            remaining === null
              ? t('common.none')
              : `${Math.abs(remaining)} ${t('common.kcal')}`
          "
          :trend="remaining === null ? undefined : isOver ? 'down' : 'up'"
          :trend-label="
            today.target ? t('dashboard.budget', { kcal: Math.round(today.budgetKcal ?? 0) }) : undefined
          "
        />
      </div>

      <!-- The week's chart gets the full width: seven bars and their budget
           markers need the room to stay readable. -->
      <DsCard class="block">
        <WeeklyChart :days="data.history" />

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

      <DsCard class="block">
        <h3>{{ t('dashboard.macrosToday') }}</h3>
        <MacroBars :consumed="today.consumed" :target="today.target?.macros ?? null" />

        <p v-if="today.target" class="small muted target-detail">
          {{
            t('dashboard.targetExplain', {
              target: today.target.targetKcal,
              bmr: today.target.bmr,
              tdee: today.target.tdee,
              formula: t(`formula.${today.target.formula}`),
            })
          }}
        </p>
      </DsCard>

      <DsCard class="block">
        <div class="row-between">
          <h3>{{ t('dashboard.todaysEntries') }}</h3>
          <RouterLink to="/diary" class="small">{{ t('dashboard.addSomething') }}</RouterLink>
        </div>

        <p v-if="data.recentEntries.length === 0" class="empty">
          {{ t('dashboard.nothingToday') }}
        </p>

        <DsTable v-else v-slot="{ styles }">
          <thead>
            <tr>
              <th :class="styles.headCell" scope="col">{{ t('dashboard.tableFood') }}</th>
              <th :class="styles.headCell" scope="col">{{ t('dashboard.tableMeal') }}</th>
              <th :class="[styles.headCell, 'num']" scope="col">{{ t('dashboard.tableAmount') }}</th>
              <th :class="[styles.headCell, 'num']" scope="col">{{ t('common.kcal') }}</th>
              <th :class="[styles.headCell, 'num']" scope="col">{{ t('dashboard.tableMacros') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in data.recentEntries" :key="entry.id" :class="styles.tableRow">
              <td :class="styles.bodyCell">{{ entry.label }}</td>
              <td :class="styles.bodyCell">
                <DsBadge :label="t(`meal.${entry.mealType}`)" />
              </td>
              <td :class="[styles.bodyCell, 'num']">
                {{ entry.quantity }} {{ entry.portionLabel ?? entry.unit }}
              </td>
              <td :class="[styles.bodyCell, 'num']">{{ Math.round(entry.nutrients.kcal) }}</td>
              <td :class="[styles.bodyCell, 'num', 'muted']">
                {{ Math.round(entry.nutrients.proteinG) }} /
                {{ Math.round(entry.nutrients.carbsG) }} /
                {{ Math.round(entry.nutrients.fatG) }}
              </td>
            </tr>
          </tbody>
        </DsTable>
      </DsCard>

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
.onboarding { margin-bottom: var(--spacing-medium); }
.onboarding-action { display: inline-block; margin-top: var(--spacing-small); }
.onboarding-action:hover { text-decoration: none; }

.stats { margin-bottom: var(--spacing-medium); }
.block { margin-bottom: var(--spacing-medium); }

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
