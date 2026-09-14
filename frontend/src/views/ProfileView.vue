<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { api, ApiError } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import type { ActivityLevel, Goal, Sex } from '@/api/types'

/**
 * Body data, and the calorie target that follows from it.
 *
 * Split in two on purpose: the profile holds what rarely changes, weigh-ins are
 * a series. That is also what lets a past day keep the target that applied then.
 */
const auth = useAuthStore()

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

const activityLevels: { value: ActivityLevel; label: string }[] = [
  { value: 'sedentary', label: 'Sedentary — little or no exercise, desk job' },
  { value: 'lightly_active', label: 'Lightly active — exercise 1–3 days a week' },
  { value: 'moderately_active', label: 'Moderately active — exercise 3–5 days a week' },
  { value: 'very_active', label: 'Very active — hard exercise 6–7 days a week' },
  { value: 'extra_active', label: 'Extra active — physical job or training twice a day' },
]

const goals: { value: Goal; label: string }[] = [
  { value: 'lose_weight', label: 'Lose weight — 20% below maintenance' },
  { value: 'maintain_weight', label: 'Maintain weight' },
  { value: 'gain_muscle', label: 'Gain muscle — 10% above maintenance' },
]

const target = computed(() => auth.user?.dailyTarget ?? null)

const formulaName = computed(() =>
  target.value?.formula === 'katch-mcardle' ? 'Katch-McArdle' : 'Mifflin-St Jeor',
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
    if (e instanceof ApiError) {
      profileError.value = e.message
      profileViolations.value = e.violations
    } else {
      profileError.value = 'Could not save your profile.'
    }
  }
}

async function saveMeasurement(): Promise<void> {
  measurementError.value = ''
  measurementSaved.value = false

  if (measurement.weightKg === null) {
    measurementError.value = 'Enter your weight.'
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
    measurementError.value = e instanceof ApiError ? e.message : 'Could not save that weigh-in.'
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
    <h1>Your body data</h1>

    <div class="grid grid-2">
      <div class="card">
        <h2>About you</h2>
        <p class="muted small note">These rarely change, so you only fill them in once.</p>

        <form class="stack" @submit.prevent="saveProfile">
          <div>
            <label for="birthDate">Date of birth</label>
            <input id="birthDate" v-model="profile.birthDate" type="date" required />
            <p v-if="profileViolations.birthDate" class="field-error">
              {{ profileViolations.birthDate }}
            </p>
          </div>

          <div>
            <label for="sex">Sex</label>
            <select id="sex" v-model="profile.sex">
              <option value="male">Male</option>
              <option value="female">Female</option>
            </select>
            <p class="muted small hint">
              The calorie formulas use a different constant per sex, which is the
              only reason this is asked.
            </p>
          </div>

          <div>
            <label for="height">Height (cm)</label>
            <input id="height" v-model.number="profile.heightCm" type="number" min="80" max="250" required />
            <p v-if="profileViolations.heightCm" class="field-error">
              {{ profileViolations.heightCm }}
            </p>
          </div>

          <div>
            <label for="activity">How active are you?</label>
            <select id="activity" v-model="profile.activityLevel">
              <option v-for="level in activityLevels" :key="level.value" :value="level.value">
                {{ level.label }}
              </option>
            </select>
          </div>

          <div>
            <label for="goal">What are you aiming for?</label>
            <select id="goal" v-model="profile.goal">
              <option v-for="option in goals" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>

          <p v-if="profileError" class="alert alert-error">{{ profileError }}</p>
          <p v-if="profileSaved" class="alert alert-success">Saved.</p>

          <button type="submit">Save</button>
        </form>
      </div>

      <div class="stack">
        <div class="card">
          <h2>Today's weigh-in</h2>
          <p class="muted small note">
            Weighing yourself twice in one day replaces the earlier entry rather
            than adding a second one.
          </p>

          <form class="stack" @submit.prevent="saveMeasurement">
            <div>
              <label for="weight">Weight (kg)</label>
              <input id="weight" v-model.number="measurement.weightKg" type="number" min="20" max="500" step="0.1" required />
            </div>

            <div class="grid grid-2">
              <div>
                <label for="muscle">Muscle mass (kg, optional)</label>
                <input id="muscle" v-model.number="measurement.muscleMassKg" type="number" min="0" step="0.1" />
              </div>
              <div>
                <label for="fat">Fat mass (kg, optional)</label>
                <input id="fat" v-model.number="measurement.fatMassKg" type="number" min="0" step="0.1" />
              </div>
            </div>

            <p class="muted small hint">
              If you know your fat mass, the calculation switches to a formula
              based on lean mass, which is more accurate than one based on total
              weight.
            </p>

            <p v-if="measurementError" class="alert alert-error">{{ measurementError }}</p>
            <p v-if="measurementSaved" class="alert alert-success">Saved.</p>

            <button type="submit">Save weigh-in</button>
          </form>
        </div>

        <div v-if="target" class="card target-card">
          <h2>Your daily target</h2>

          <p class="stat-value">{{ target.targetKcal }}<span class="unit">kcal</span></p>

          <table class="breakdown">
            <tbody>
              <tr>
                <td>Basal metabolic rate</td>
                <td class="num">{{ target.bmr }} kcal</td>
              </tr>
              <tr>
                <td>Total daily expenditure</td>
                <td class="num">{{ target.tdee }} kcal</td>
              </tr>
              <tr>
                <td>Protein</td>
                <td class="num">{{ target.macros.proteinG }} g</td>
              </tr>
              <tr>
                <td>Carbohydrate</td>
                <td class="num">{{ target.macros.carbsG }} g</td>
              </tr>
              <tr>
                <td>Fat</td>
                <td class="num">{{ target.macros.fatG }} g</td>
              </tr>
            </tbody>
          </table>

          <p class="muted small hint">Calculated with the {{ formulaName }} formula.</p>
        </div>

        <div v-else class="card">
          <h2>Your daily target</h2>
          <p class="muted">
            Fill in your details and a weigh-in, and your target will appear here.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.note { margin: 0 0 16px; }
.hint { margin: 5px 0 0; }
.unit { font-size: 14px; font-weight: 600; color: var(--text-muted); margin-left: 5px; }
.target-card { border-left: 3px solid var(--good); }
.breakdown { margin-top: 12px; }
</style>
