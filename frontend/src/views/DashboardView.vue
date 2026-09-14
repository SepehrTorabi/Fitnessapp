<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { DashboardData } from '@/api/types'
import WeeklyChart from '@/components/WeeklyChart.vue'
import MacroBars from '@/components/MacroBars.vue'

const auth = useAuthStore()

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
    error.value = e instanceof ApiError ? e.message : 'Could not load your dashboard.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="page">
    <h1>Hello, {{ auth.user?.displayName }}</h1>

    <!-- Without body data there is no target, and the whole dashboard is empty
         numbers. Say what is missing instead of showing zeroes. -->
    <div v-if="auth.needsProfile || auth.needsWeight" class="card onboarding">
      <h2>One more step</h2>
      <p class="muted">
        {{
          auth.needsProfile
            ? 'Tell us your age, height and activity level so we can work out how much you need.'
            : 'Add your current weight so we can work out your daily calories.'
        }}
      </p>
      <RouterLink to="/profile"><button type="button">Fill in your body data</button></RouterLink>
    </div>

    <p v-if="loading" class="muted">Loading…</p>
    <p v-else-if="error" class="alert alert-error">{{ error }}</p>

    <template v-else-if="data && today">
      <div class="grid grid-3 stats">
        <div class="card">
          <p class="stat-label">Eaten today</p>
          <p class="stat-value">{{ Math.round(today.consumed.kcal) }}<span class="unit">kcal</span></p>
        </div>

        <div class="card">
          <p class="stat-label">Burned through activity</p>
          <p class="stat-value">{{ Math.round(today.caloriesBurned) }}<span class="unit">kcal</span></p>
        </div>

        <div class="card">
          <p class="stat-label">
            {{ remaining !== null && remaining < 0 ? 'Over budget' : 'Left today' }}
          </p>
          <p
            class="stat-value"
            :class="remaining === null ? '' : remaining < 0 ? 'value-over' : 'value-good'"
          >
            {{ remaining === null ? '—' : Math.abs(remaining) }}<span class="unit">kcal</span>
          </p>
          <p v-if="today.target" class="small muted">
            Budget {{ Math.round(today.budgetKcal ?? 0) }} kcal
          </p>
        </div>
      </div>

      <!-- The week's chart gets the full width: seven bars and their budget
           markers need the room to stay readable. -->
      <div class="card main">
        <WeeklyChart :days="data.history" />
      </div>

      <div class="card main">
        <h3>Macros today</h3>
        <MacroBars :consumed="today.consumed" :target="today.target?.macros ?? null" />

        <div v-if="today.target" class="target-detail">
          <p class="small muted">
            Target {{ today.target.targetKcal }} kcal — basal rate
            {{ today.target.bmr }}, daily expenditure {{ today.target.tdee }}, calculated with
            {{ today.target.formula === 'katch-mcardle' ? 'Katch-McArdle' : 'Mifflin-St Jeor' }}.
          </p>
        </div>
      </div>

      <div class="card">
        <div class="row-between">
          <h3>Today's entries</h3>
          <RouterLink to="/diary" class="small">Add something</RouterLink>
        </div>

        <p v-if="data.recentEntries.length === 0" class="empty">
          Nothing logged today yet.
        </p>

        <table v-else>
          <thead>
            <tr>
              <th>Food</th>
              <th>Meal</th>
              <th class="num">Amount</th>
              <th class="num">kcal</th>
              <th class="num">P / C / F</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in data.recentEntries" :key="entry.id">
              <td>{{ entry.label }}</td>
              <td class="muted">{{ entry.mealType }}</td>
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

      <p v-if="data.averages.daysLogged > 0" class="muted small averages">
        Over the {{ data.averages.daysLogged }}
        {{ data.averages.daysLogged === 1 ? 'day' : 'days' }} you logged this week you averaged
        {{ data.averages.kcal }} kcal, {{ data.averages.proteinG }} g protein.
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
</style>
