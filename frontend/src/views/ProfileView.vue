<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api, ApiError } from '@/api/client'
import { useApiMessage } from '@/composables/useApiMessage'
import { useAuthStore } from '@/stores/auth'
import type { ActivityLevel, Goal, Sex } from '@/api/types'
import { useNumbers } from '@/composables/useNumbers'

/**
 * Body data, and the calorie target that follows from it.
 *
 * Split in two on purpose: the profile holds what rarely changes, weigh-ins are
 * a series. That is also what lets a past day keep the target that applied then.
 */
const auth = useAuthStore()
const { t } = useI18n()
const apiMessage = useApiMessage()
const { n, decimal } = useNumbers()

const profile = reactive({
  birthDate: '',
  sex: 'male' as Sex,
  heightCm: null as number | null,
  activityLevel: 'moderately_active' as ActivityLevel,
  goal: 'maintain_weight' as Goal,
})

const measurement = reactive({
  weightKg: null as number | null,
  muscleMassKg: null as number | null,
  fatMassKg: null as number | null,
})

const profileError = ref('')
const profileViolations = ref<Record<string, string>>({})
const profileSaved = ref(false)
const measurementError = ref('')
const measurementSaved = ref(false)

// Computed rather than constant: the labels have to be re-read when the
// language changes, and a plain array would keep whatever it was built with.
const ACTIVITY_LEVELS: ActivityLevel[] = [
  'sedentary',
  'lightly_active',
  'moderately_active',
  'very_active',
  'extra_active',
]

/**
 * Ordered from the biggest deficit to the biggest surplus, with the two
 * training-led goals last. A dropdown of eight is worth ordering deliberately:
 * this way the list reads as a scale, and somebody looking for "a bit less
 * aggressive than what I have now" finds it next to what they have now.
 */
const GOALS: Goal[] = [
  'lose_weight',
  'lose_fat_slowly',
  'maintain_weight',
  'recomposition',
  'gain_muscle',
  'gain_weight',
  'endurance',
  'strength',
]

const activityLevels = computed(() =>
  ACTIVITY_LEVELS.map((value) => ({ value, label: t(`activityLevel.${value}`) })),
)

const goals = computed(() => GOALS.map((value) => ({ value, label: t(`goal.${value}`) })))

const target = computed(() => auth.user?.dailyTarget ?? null)

const formulaName = computed(() =>
  target.value ? t(`formula.${target.value.formula}`) : '',
)

async function saveProfile(): Promise<void> {
  profileError.value = ''
  profileViolations.value = {}
  profileSaved.value = false

  try {
    await api.updateProfile({
      birthDate: profile.birthDate,
      sex: profile.sex,
      heightCm: profile.heightCm ?? 0,
      activityLevel: profile.activityLevel,
      goal: profile.goal,
    })
    await auth.refresh()
    profileSaved.value = true
  } catch (e) {
    profileError.value = apiMessage(e, 'profile.saveFailed')
    if (e instanceof ApiError) profileViolations.value = e.violations
  }
}

async function saveMeasurement(): Promise<void> {
  measurementError.value = ''
  measurementSaved.value = false

  if (measurement.weightKg === null) {
    measurementError.value = t('profile.weightRequired')
    return
  }

  try {
    await api.addMeasurement({
      weightKg: measurement.weightKg,
      muscleMassKg: measurement.muscleMassKg,
      fatMassKg: measurement.fatMassKg,
    })
    await auth.refresh()
    measurementSaved.value = true
  } catch (e) {
    measurementError.value = apiMessage(e, 'profile.weighInFailed')
  }
}

onMounted(() => {
  // Prefill from what the API already knows, so this is an edit form rather than
  // a blank one every time.
  const existing = auth.user?.profile
  if (existing) {
    profile.birthDate = existing.birthDate
    profile.sex = existing.sex
    profile.heightCm = existing.heightCm
    profile.activityLevel = existing.activityLevel
    profile.goal = existing.goal
  }

  const latest = auth.user?.latestMeasurement
  if (latest) {
    measurement.weightKg = latest.weightKg
    measurement.muscleMassKg = latest.muscleMassKg
    measurement.fatMassKg = latest.fatMassKg
  }
})
</script>

<template>
  <div class="page">
    <h1>{{ t('titles.profile') }}</h1>

    <div class="grid grid-2">
      <div class="card">
        <h2>{{ t('profile.aboutYou') }}</h2>
        <p class="muted small note">{{ t('profile.aboutIntro') }}</p>

        <form class="stack" @submit.prevent="saveProfile">
          <div>
            <label for="birthDate">{{ t('profile.birthDate') }}</label>
            <input id="birthDate" v-model="profile.birthDate" type="date" required />
            <p v-if="profileViolations.birthDate" class="field-error">
              {{ profileViolations.birthDate }}
            </p>
          </div>

          <div>
            <label for="sex">{{ t('profile.sex') }}</label>
            <select id="sex" v-model="profile.sex">
              <option value="male">{{ t('sex.male') }}</option>
              <option value="female">{{ t('sex.female') }}</option>
            </select>
            <p class="muted small hint">
              {{ t('profile.sexHint') }}
            </p>
          </div>

          <div>
            <label for="height">{{ t('profile.height') }}</label>
            <input id="height" v-model.number="profile.heightCm" type="number" min="80" max="250" required />
            <p v-if="profileViolations.heightCm" class="field-error">
              {{ profileViolations.heightCm }}
            </p>
          </div>

          <div>
            <label for="activity">{{ t('profile.activityQuestion') }}</label>
            <select id="activity" v-model="profile.activityLevel">
              <option v-for="level in activityLevels" :key="level.value" :value="level.value">
                {{ level.label }}
              </option>
            </select>
          </div>

          <div>
            <label for="goal">{{ t('profile.goalQuestion') }}</label>
            <select id="goal" v-model="profile.goal">
              <option v-for="option in goals" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>

          <p v-if="profileError" class="alert alert-error">{{ profileError }}</p>
          <p v-if="profileSaved" class="alert alert-success">{{ t('common.saved') }}</p>

          <button type="submit">{{ t('common.save') }}</button>
        </form>
      </div>

      <div class="stack">
        <div class="card">
          <h2>{{ t('profile.weighIn') }}</h2>
          <p class="muted small note">
            {{ t('profile.weighInIntro') }}
          </p>

          <form class="stack" @submit.prevent="saveMeasurement">
            <div>
              <label for="weight">{{ t('profile.weight') }}</label>
              <input id="weight" v-model.number="measurement.weightKg" type="number" min="20" max="500" step="0.1" required />
            </div>

            <div class="grid grid-2">
              <div>
                <label for="muscle">{{ t('profile.muscleMass') }}</label>
                <input id="muscle" v-model.number="measurement.muscleMassKg" type="number" min="0" step="0.1" />
              </div>
              <div>
                <label for="fat">{{ t('profile.fatMass') }}</label>
                <input id="fat" v-model.number="measurement.fatMassKg" type="number" min="0" step="0.1" />
              </div>
            </div>

            <p class="muted small hint">
              {{ t('profile.fatMassHint') }}
            </p>

            <p v-if="measurementError" class="alert alert-error">{{ measurementError }}</p>
            <p v-if="measurementSaved" class="alert alert-success">{{ t('common.saved') }}</p>

            <button type="submit">{{ t('profile.saveWeighIn') }}</button>
          </form>
        </div>

        <div v-if="target" class="card target-card">
          <h2>{{ t('profile.dailyTarget') }}</h2>

          <p class="stat-value">
            {{ n(target.targetKcal) }}<span class="unit">{{ t('common.kcal') }}</span>
          </p>

          <table class="breakdown">
            <tbody>
              <tr>
                <td>{{ t('profile.bmr') }}</td>
                <td class="num">{{ n(target.bmr) }} {{ t('common.kcal') }}</td>
              </tr>
              <tr>
                <td>{{ t('profile.tdee') }}</td>
                <td class="num">{{ n(target.tdee) }} {{ t('common.kcal') }}</td>
              </tr>
              <tr>
                <td>{{ t('profile.proteinRow') }}</td>
                <td class="num">{{ decimal(target.macros.proteinG) }} {{ t('common.grams') }}</td>
              </tr>
              <tr>
                <td>{{ t('profile.carbsRow') }}</td>
                <td class="num">{{ decimal(target.macros.carbsG) }} {{ t('common.grams') }}</td>
              </tr>
              <tr>
                <td>{{ t('profile.fatRow') }}</td>
                <td class="num">{{ decimal(target.macros.fatG) }} {{ t('common.grams') }}</td>
              </tr>
            </tbody>
          </table>

          <p class="muted small hint">{{ t('profile.calculatedWith', { formula: formulaName }) }}</p>
        </div>

        <div v-else class="card">
          <h2>{{ t('profile.dailyTarget') }}</h2>
          <p class="muted">{{ t('profile.noTargetYet') }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.note { margin: 0 0 16px; }
.hint { margin: 5px 0 0; }
.unit { font-size: 14px; font-weight: 600; color: var(--text-muted); margin-inline-start: 5px; }
.target-card { border-inline-start: 3px solid var(--good); }
.breakdown { margin-top: 12px; }
</style>
