<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '@/api/client'
import { useApiMessage } from '@/composables/useApiMessage'
import type { DayView, ExternalFood, Food, MealType, Recipe } from '@/api/types'
import MacroBars from '@/components/MacroBars.vue'
import DateField from '@/components/DateField.vue'
import { todayIso } from '@/calendar'
import { useNumbers } from '@/composables/useNumbers'

/**
 * The barcode reader pulls in ZXing, which is around 400 kB - several times the
 * weight of the rest of the app. Loading it only once the user actually opens
 * the scanner keeps that off the critical path for everyone who logs food by
 * searching, which is most of the time.
 */
const BarcodeScanner = defineAsyncComponent(() => import('@/components/BarcodeScanner.vue'))

/**
 * Where food gets logged.
 *
 * Three ways in, because a single one never covers every case: search the
 * catalogue, scan a barcode, or pick one of your own recipes.
 *
 * Activity used to be logged at the bottom of this page. It has a page of its
 * own now: training is not a kind of eating, and burying the form under the
 * food form meant only people already logging a meal ever found it.
 */

const { t } = useI18n()
const apiMessage = useApiMessage()
const { n } = useNumbers()

// Built from the local date rather than from toISOString(), which is UTC: west
// of Greenwich in the evening the two are different days, and the diary would
// open on tomorrow.
const today = todayIso()
const date = ref(today)

const day = ref<DayView | null>(null)
const loading = ref(true)
const error = ref('')

// --- Food search ---
const query = ref('')
const searching = ref(false)
const localResults = ref<Food[]>([])
const externalResults = ref<ExternalFood[]>([])
const searchNote = ref('')

// --- The food currently being logged ---
const selected = ref<Food | null>(null)
const quantity = ref(100)
const unit = ref('g')
const mealType = ref<MealType>('breakfast')
const saving = ref(false)
const saveError = ref('')

const showScanner = ref(false)

// --- Recipes ---
const recipes = ref<Recipe[]>([])

/**
 * How a recipe is being measured, and how much of it.
 *
 * Two ways, because "a serving" is a portion whoever wrote the recipe down
 * decided on, and what actually ends up on a plate rarely agrees with it. Every
 * ingredient was entered as a weight, so the finished dish has one too - which
 * is what makes weighing possible at all.
 *
 * The amount is kept per unit rather than shared, so switching from 1 serving
 * to grams does not ask for 1 gram of lasagne.
 */
const recipeUnit = ref<'portion' | 'g'>('portion')
const recipeServings = ref(1)
const recipeGrams = ref(200)

const recipeAmount = computed(() =>
  recipeUnit.value === 'g' ? recipeGrams.value : recipeServings.value,
)

/** What logging this recipe at the current amount would add. */
function recipeKcal(recipe: Recipe): number {
  return recipeUnit.value === 'g'
    ? (recipe.per100.kcal * recipeGrams.value) / 100
    : recipe.perServing.kcal * recipeServings.value
}

const mealTypes: MealType[] = ['breakfast', 'lunch', 'dinner', 'snack']

/**
 * The units this particular food can be measured in. Driven by the food rather
 * than by a fixed list, so "slice" only appears for things that have slices.
 */
const unitOptions = computed(() => selected.value?.availableUnits ?? [])

/**
 * A live preview of what is about to be logged, computed from the food's own
 * per-100 g values and the chosen unit. The server does this again authoritatively
 * on save - this is only so the user can sanity-check before committing.
 */
const preview = computed(() => {
  if (!selected.value || quantity.value <= 0) return null

  const chosen = unitOptions.value.find((u) => u.label === unit.value)
  if (!chosen || chosen.grams === null) return null

  const grams = chosen.label === 'g' ? quantity.value : quantity.value * chosen.grams
  const factor = grams / 100

  return {
    grams,
    kcal: selected.value.per100.kcal * factor,
    proteinG: selected.value.per100.proteinG * factor,
    carbsG: selected.value.per100.carbsG * factor,
    fatG: selected.value.per100.fatG * factor,
  }
})

async function loadDay(): Promise<void> {
  loading.value = true
  error.value = ''

  try {
    day.value = await api.day(date.value)
  } catch (e) {
    error.value = apiMessage(e, 'diary.loadDayFailed')
  } finally {
    loading.value = false
  }
}

async function loadRecipes(): Promise<void> {
  try {
    recipes.value = (await api.recipes()).recipes
  } catch {
    // Recipes are a convenience on this page; failing to load them must not
    // stop the user logging food by search or barcode.
    recipes.value = []
  }
}

async function search(): Promise<void> {
  if (query.value.trim().length < 2) {
    searchNote.value = t('diary.queryTooShort')
    return
  }

  searching.value = true
  searchNote.value = ''

  try {
    const results = await api.searchFoods(query.value)
    localResults.value = results.local
    externalResults.value = results.external

    // "Nothing found" and "could not ask" are different answers and deserve
    // different words: the first means define the food yourself, the second
    // means wait a moment and try again.
    if (!results.externalAvailable) {
      searchNote.value = t('diary.externalUnavailable')
    } else if (results.local.length === 0 && results.external.length === 0) {
      searchNote.value = t('diary.nothingFound')
    }
  } catch (e) {
    searchNote.value = apiMessage(e, 'diary.searchFailed')
  } finally {
    searching.value = false
  }
}

function selectFood(food: Food): void {
  selected.value = food
  saveError.value = ''

  // Default to grams, which every food supports.
  unit.value = 'g'
  quantity.value = 100
}

/** An external hit has no id yet, so it has to be imported before it can be logged. */
async function importAndSelect(external: ExternalFood): Promise<void> {
  try {
    selectFood((await api.importFood(external.externalId)).food)
  } catch (e) {
    searchNote.value = apiMessage(e, 'diary.importFailed')
  }
}

async function onBarcode(barcode: string): Promise<void> {
  searchNote.value = ''

  try {
    selectFood((await api.foodByBarcode(barcode)).food)
  } catch (e) {
    searchNote.value = apiMessage(e, 'diary.barcodeFailed')
  }
}

async function logFood(): Promise<void> {
  if (!selected.value) return

  saving.value = true
  saveError.value = ''

  const chosen = unitOptions.value.find((u) => u.label === unit.value)

  try {
    await api.addEntry({
      foodId: selected.value.id,
      mealType: mealType.value,
      quantity: quantity.value,
      // Named portions are sent as the generic "portion" unit plus the label;
      // the server resolves the label against that food's own definitions.
      unit: chosen?.unit ?? 'g',
      portionLabel: chosen && chosen.unit === 'portion' ? chosen.label : null,
      loggedOn: date.value,
    })

    selected.value = null
    query.value = ''
    localResults.value = []
    externalResults.value = []
    await loadDay()
  } catch (e) {
    saveError.value = apiMessage(e, 'diary.saveFailed')
  } finally {
    saving.value = false
  }
}

async function logRecipe(recipe: Recipe): Promise<void> {
  saveError.value = ''

  try {
    await api.addEntry({
      recipeId: recipe.id,
      mealType: mealType.value,
      quantity: recipeAmount.value,
      unit: recipeUnit.value,
      loggedOn: date.value,
    })
    await loadDay()
  } catch (e) {
    saveError.value = apiMessage(e, 'diary.recipeFailed')
  }
}


async function removeEntry(id: number): Promise<void> {
  await api.deleteEntry(id)
  await loadDay()
}


watch(date, loadDay)

onMounted(async () => {
  await Promise.all([loadDay(), loadRecipes()])
})
</script>

<template>
  <div class="page">
    <div class="row-between">
      <h1>{{ t('titles.diary') }}</h1>
      <!-- The same picker as the dashboard, so both honour the chosen calendar.
           A native date input cannot: it is Gregorian in every browser. -->
      <DateField id="diary-date" v-model="date" :max="today" :label="t('common.day')" />
    </div>

    <p v-if="error" class="alert alert-error">{{ error }}</p>

    <div class="grid grid-2">
      <!-- ---------- Adding something ---------- -->
      <div class="stack">
        <div class="card">
          <h3>{{ t('diary.findFood') }}</h3>

          <form class="row" @submit.prevent="search">
            <input v-model="query" type="search" :placeholder="t('diary.searchPlaceholder')" />
            <button type="submit" :disabled="searching">
              {{ searching ? t('common.searching') : t('common.search') }}
            </button>
          </form>

          <p v-if="searchNote" class="alert alert-info small note">{{ searchNote }}</p>

          <ul v-if="localResults.length" class="results">
            <li v-for="food in localResults" :key="food.id">
              <button class="result" type="button" @click="selectFood(food)">
                <span class="result-name">{{ food.label }}</span>
                <span class="muted small">
                  {{ t('diary.perHundred', { kcal: n(food.per100.kcal) }) }}
                </span>
              </button>
            </li>
          </ul>

          <!-- Kept visually separate: these are not in our database yet, so
               picking one imports it first. -->
          <template v-if="externalResults.length">
            <p class="muted small section-label">{{ t('diary.externalHeading') }}</p>
            <ul class="results">
              <li v-for="food in externalResults" :key="food.externalId">
                <button class="result" type="button" @click="importAndSelect(food)">
                  <span class="result-name">{{ food.label }}</span>
                  <span class="muted small">
                  {{ t('diary.perHundred', { kcal: n(food.per100.kcal) }) }}
                </span>
                </button>
              </li>
            </ul>
          </template>
        </div>

        <div class="card">
          <h3>{{ t('diary.scanTitle') }}</h3>

          <template v-if="showScanner">
            <BarcodeScanner @detected="onBarcode" />
          </template>
          <template v-else>
            <p class="muted small note">
              {{ t('diary.scanIntro') }}
            </p>
            <button class="secondary" type="button" @click="showScanner = true">
              {{ t('diary.openScanner') }}
            </button>
          </template>
        </div>

        <div v-if="recipes.length" class="card">
          <h3>{{ t('diary.yourRecipes') }}</h3>

          <!-- Amount and unit together, so the number on each row below always
               says what clicking it would actually add. -->
          <div class="row amount-row recipe-amount">
            <div class="amount-field">
              <label for="recipe-amount">{{ t('diary.amount') }}</label>
              <input
                v-if="recipeUnit === 'g'"
                id="recipe-amount"
                v-model.number="recipeGrams"
                type="number"
                min="1"
                step="10"
              />
              <input
                v-else
                id="recipe-amount"
                v-model.number="recipeServings"
                type="number"
                min="0.25"
                step="0.25"
              />
            </div>

            <div class="amount-field">
              <label for="recipe-unit">{{ t('diary.unit') }}</label>
              <select id="recipe-unit" v-model="recipeUnit">
                <option value="portion">{{ t('diary.servings') }}</option>
                <!-- A noun, not the unit suffix: "Servings / g" reads as two
                     different kinds of word in the same dropdown. -->
                <option value="g">{{ t('diary.gramsUnit') }}</option>
              </select>
            </div>
          </div>

          <ul class="results">
            <li v-for="recipe in recipes" :key="recipe.id">
              <button class="result" type="button" @click="logRecipe(recipe)">
                <span class="result-name">{{ recipe.name }}</span>
                <span class="muted small">
                  <!-- What this click adds, not an abstract rate: the amount is
                       already chosen above, so showing it resolved saves the
                       user doing the multiplication. -->
                  {{ t('diary.recipeAdds', { kcal: n(recipeKcal(recipe)) }) }}
                </span>
              </button>
            </li>
          </ul>
        </div>
      </div>

      <!-- ---------- The day so far ---------- -->
      <div class="stack">
        <div v-if="selected" class="card selected-card">
          <h3>{{ selected.label }}</h3>

          <div class="row amount-row">
            <div class="amount-field">
              <label for="quantity">{{ t('diary.amount') }}</label>
              <input id="quantity" v-model.number="quantity" type="number" min="0.1" step="any" />
            </div>

            <div class="amount-field">
              <label for="unit">{{ t('diary.unit') }}</label>
              <select id="unit" v-model="unit">
                <option v-for="option in unitOptions" :key="option.label" :value="option.label">
                  {{ option.label }}
                </option>
              </select>
            </div>

            <div class="amount-field">
              <label for="meal">{{ t('diary.meal') }}</label>
              <select id="meal" v-model="mealType">
                <option v-for="type in mealTypes" :key="type" :value="type">
                  {{ t(`meal.${type}`) }}
                </option>
              </select>
            </div>
          </div>

          <p v-if="preview" class="preview">
            <strong>{{ n(preview.kcal) }} {{ t('common.kcal') }}</strong>
            <span class="muted">
              {{
                t('diary.preview', {
                  grams: n(preview.grams),
                  protein: n(preview.proteinG),
                  carbs: n(preview.carbsG),
                  fat: n(preview.fatG),
                })
              }}
            </span>
          </p>

          <p v-if="saveError" class="alert alert-error small">{{ saveError }}</p>

          <div class="row">
            <button type="button" :disabled="saving" @click="logFood">
              {{ saving ? t('diary.adding') : t('diary.addToDiary') }}
            </button>
            <button class="secondary" type="button" @click="selected = null">
              {{ t('common.cancel') }}
            </button>
          </div>
        </div>

        <div v-if="day" class="card">
          <h3>{{ t('diary.totals') }}</h3>
          <p class="stat-value">
            {{ n(day.summary.consumed.kcal) }}<span class="unit">{{ t('common.kcal') }}</span>
            <span
              v-if="day.summary.withinBudget !== null"
              class="badge"
              :class="day.summary.withinBudget ? 'badge-good' : 'badge-over'"
            >
              {{
                day.summary.withinBudget
                  ? t('diary.left', { kcal: n(day.summary.remainingKcal ?? 0) })
                  : t('diary.over', { kcal: n(Math.abs(day.summary.remainingKcal ?? 0)) })
              }}
            </span>
          </p>

          <MacroBars
            :consumed="day.summary.consumed"
            :target="day.summary.target?.macros ?? null"
          />
        </div>

        <div v-if="day" class="card">
          <h3>{{ t('diary.entries') }}</h3>

          <p v-if="loading" class="muted">{{ t('common.loading') }}</p>
          <p v-else-if="day.entries.length === 0" class="empty">{{ t('diary.nothingLogged') }}</p>

          <table v-else>
            <tbody>
              <tr v-for="entry in day.entries" :key="entry.id">
                <td>
                  {{ entry.label }}
                  <span class="muted small block">
                    {{ entry.quantity }} {{ entry.portionLabel ?? entry.unit }} ·
                    {{ t(`meal.${entry.mealType}`) }}
                  </span>
                </td>
                <td class="num">{{ n(entry.nutrients.kcal) }} {{ t('common.kcal') }}</td>
                <td class="num shrink">
                  <button class="ghost" type="button" :title="t('common.remove')" @click="removeEntry(entry.id)">
                    ✕
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
.note { margin: 10px 0 0; }
.section-label { margin: 16px 0 6px; font-weight: 600; }

.results { list-style: none; margin: 12px 0 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }

.result {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 12px;
  text-align: start;
  background: var(--surface-2);
  color: var(--text);
  border: 1px solid transparent;
  font-weight: 500;
  padding: 9px 12px;
}

.result:hover { border-color: var(--accent); }
.result-name { font-weight: 600; }

.selected-card { border-inline-start: 3px solid var(--accent); }

.amount-row { align-items: flex-end; }
.amount-field { flex: 1; min-width: 110px; }

.preview {
  background: var(--surface-2);
  border-radius: var(--radius-sm);
  padding: 10px 12px;
  margin: 14px 0;
  font-size: 14px;
}

.recipe-amount { align-items: flex-end; margin-bottom: 4px; }

.unit { font-size: 14px; font-weight: 600; color: var(--text-muted); margin: 0 10px 0 5px; }
.block { display: block; }
.shrink { width: 1%; white-space: nowrap; }
</style>
