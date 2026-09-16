<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api, ApiError } from '@/api/client'
import { useApiMessage } from '@/composables/useApiMessage'
import type { Food, Recipe } from '@/api/types'
import { useNumbers } from '@/composables/useNumbers'

/**
 * Defining new foods and building recipes.
 *
 * Anything created here goes into the catalogue, so it turns up in the search on
 * the logging page from then on.
 */

const { t } = useI18n()
const apiMessage = useApiMessage()
const { n } = useNumbers()

type Tab = 'food' | 'recipe'
const tab = ref<Tab>('food')

// --- New food ---
const food = reactive({
  name: '',
  brand: '',
  barcode: '',
  densityGPerMl: 1,
  kcal: null as number | null,
  proteinG: null as number | null,
  carbsG: null as number | null,
  fatG: null as number | null,
  fiberG: null as number | null,
  sugarG: null as number | null,
  saltG: null as number | null,
})

const portions = ref<{ label: string; grams: number | null }[]>([])

const foodError = ref('')
const foodViolations = ref<Record<string, string>>({})
const foodWarnings = ref<string[]>([])
const foodSuccess = ref('')
const savingFood = ref(false)

/**
 * What the macros imply in calories. Shown live so a value typed per serving
 * instead of per 100 g is obvious before saving rather than after.
 */
const impliedKcal = computed(
  () =>
    (food.proteinG ?? 0) * 4 + (food.carbsG ?? 0) * 4 + (food.fatG ?? 0) * 9,
)

const energyMismatch = computed(() => {
  if (!food.kcal || food.kcal <= 0) return false

  return Math.abs(food.kcal - impliedKcal.value) / food.kcal > 0.25
})

function addPortion(): void {
  portions.value.push({ label: '', grams: null })
}

function removePortion(index: number): void {
  portions.value.splice(index, 1)
}

async function saveFood(): Promise<void> {
  savingFood.value = true
  foodError.value = ''
  foodViolations.value = {}
  foodWarnings.value = []
  foodSuccess.value = ''

  try {
    const response = await api.createFood({
      name: food.name,
      brand: food.brand || null,
      barcode: food.barcode || null,
      densityGPerMl: food.densityGPerMl,
      per100: {
        kcal: food.kcal,
        proteinG: food.proteinG,
        carbsG: food.carbsG,
        fatG: food.fatG,
        fiberG: food.fiberG,
        sugarG: food.sugarG,
        saltG: food.saltG,
      },
      portions: portions.value
        .filter((p) => p.label.trim() !== '' && p.grams !== null)
        .map((p) => ({ label: p.label.trim(), grams: p.grams })),
    })

    foodSuccess.value = t('foods.foodSaved', { label: response.food.label })
    foodWarnings.value = response.warnings

    Object.assign(food, {
      name: '', brand: '', barcode: '', densityGPerMl: 1,
      kcal: null, proteinG: null, carbsG: null, fatG: null,
      fiberG: null, sugarG: null, saltG: null,
    })
    portions.value = []
  } catch (e) {
    foodError.value = apiMessage(e, 'foods.saveFoodFailed')
    if (e instanceof ApiError) foodViolations.value = e.violations
  } finally {
    savingFood.value = false
  }
}

// --- New recipe ---
const recipe = reactive({ name: '', description: '', servings: 1, public: false })
const ingredients = ref<{ food: Food; grams: number }[]>([])

const ingredientQuery = ref('')
const ingredientResults = ref<Food[]>([])
const recipeError = ref('')
const recipeSuccess = ref('')
const recipes = ref<Recipe[]>([])

/** Live totals from the ingredient list, the same sum the server will compute. */
const recipeTotals = computed(() => {
  const total = ingredients.value.reduce(
    (acc, item) => {
      const factor = item.grams / 100
      return {
        grams: acc.grams + item.grams,
        kcal: acc.kcal + item.food.per100.kcal * factor,
        proteinG: acc.proteinG + item.food.per100.proteinG * factor,
        carbsG: acc.carbsG + item.food.per100.carbsG * factor,
        fatG: acc.fatG + item.food.per100.fatG * factor,
      }
    },
    { grams: 0, kcal: 0, proteinG: 0, carbsG: 0, fatG: 0 },
  )

  const servings = Math.max(1, recipe.servings)

  return {
    ...total,
    perServing: {
      grams: total.grams / servings,
      kcal: total.kcal / servings,
      proteinG: total.proteinG / servings,
      carbsG: total.carbsG / servings,
      fatG: total.fatG / servings,
    },
  }
})

async function searchIngredient(): Promise<void> {
  if (ingredientQuery.value.trim().length < 2) return

  try {
    ingredientResults.value = (await api.searchFoods(ingredientQuery.value)).local
  } catch {
    ingredientResults.value = []
  }
}

function addIngredient(item: Food): void {
  ingredients.value.push({ food: item, grams: 100 })
  ingredientResults.value = []
  ingredientQuery.value = ''
}

async function saveRecipe(): Promise<void> {
  recipeError.value = ''
  recipeSuccess.value = ''

  if (ingredients.value.length === 0) {
    recipeError.value = t('foods.needIngredient')
    return
  }

  try {
    const response = await api.createRecipe({
      name: recipe.name,
      servings: recipe.servings,
      description: recipe.description || null,
      public: recipe.public,
      ingredients: ingredients.value.map((i) => ({ foodId: i.food.id, grams: i.grams })),
    })

    recipeSuccess.value = t('foods.recipeSaved', {
      name: response.recipe.name,
      kcal: n(response.recipe.perServing.kcal),
    })

    Object.assign(recipe, { name: '', description: '', servings: 1, public: false })
    ingredients.value = []
    await loadRecipes()
  } catch (e) {
    recipeError.value = apiMessage(e, 'foods.saveRecipeFailed')
  }
}

async function loadRecipes(): Promise<void> {
  try {
    recipes.value = (await api.recipes()).recipes
  } catch {
    recipes.value = []
  }
}

async function deleteRecipe(id: number): Promise<void> {
  await api.deleteRecipe(id)
  await loadRecipes()
}

onMounted(loadRecipes)
</script>

<template>
  <div class="page">
    <h1>{{ t('nav.foods') }}</h1>

    <div class="tabs">
      <button
        :class="tab === 'food' ? 'primary' : 'secondary'"
        type="button"
        @click="tab = 'food'"
      >
        {{ t('foods.newFood') }}
      </button>
      <button
        :class="tab === 'recipe' ? 'primary' : 'secondary'"
        type="button"
        @click="tab = 'recipe'"
      >
        {{ t('foods.newRecipe') }}
      </button>
    </div>

    <!-- ---------- New food ---------- -->
    <div v-if="tab === 'food'" class="card">
      <h2>{{ t('foods.defineFood') }}</h2>
      <p class="muted small note">
        {{ t('foods.defineIntro') }}
      </p>

      <form class="stack" @submit.prevent="saveFood">
        <div class="grid grid-3">
          <div>
            <label for="fname">{{ t('foods.name') }}</label>
            <input id="fname" v-model="food.name" type="text" required :aria-invalid="Boolean(foodViolations.name)" />
            <p v-if="foodViolations.name" class="field-error">{{ foodViolations.name }}</p>
          </div>
          <div>
            <label for="fbrand">{{ t('foods.brand') }}</label>
            <input id="fbrand" v-model="food.brand" type="text" />
          </div>
          <div>
            <label for="fbarcode">{{ t('foods.barcode') }}</label>
            <input id="fbarcode" v-model="food.barcode" type="text" inputmode="numeric" />
            <p v-if="foodViolations.barcode" class="field-error">{{ foodViolations.barcode }}</p>
          </div>
        </div>

        <div class="grid grid-3">
          <div>
            <label for="fkcal">{{ t('foods.calories') }}</label>
            <input id="fkcal" v-model.number="food.kcal" type="number" min="0" step="any" required />
          </div>
          <div>
            <label for="fprotein">{{ t('foods.protein') }}</label>
            <input id="fprotein" v-model.number="food.proteinG" type="number" min="0" step="any" required />
          </div>
          <div>
            <label for="fcarbs">{{ t('foods.carbs') }}</label>
            <input id="fcarbs" v-model.number="food.carbsG" type="number" min="0" step="any" required />
          </div>
          <div>
            <label for="ffat">{{ t('foods.fat') }}</label>
            <input id="ffat" v-model.number="food.fatG" type="number" min="0" step="any" required />
          </div>
          <div>
            <label for="ffiber">{{ t('foods.fiber') }}</label>
            <input id="ffiber" v-model.number="food.fiberG" type="number" min="0" step="any" />
          </div>
          <div>
            <label for="fsugar">{{ t('foods.sugar') }}</label>
            <input id="fsugar" v-model.number="food.sugarG" type="number" min="0" step="any" />
          </div>
        </div>

        <!-- Warn while typing rather than only on save. -->
        <p v-if="energyMismatch" class="alert alert-info small">
          {{ t('foods.energyMismatch', { implied: n(impliedKcal), stated: n(food.kcal) }) }}
        </p>

        <div>
          <label for="fdensity">{{ t('foods.density') }}</label>
          <input id="fdensity" v-model.number="food.densityGPerMl" type="number" min="0.1" max="5" step="0.01" />
          <p class="muted small hint">
            {{ t('foods.densityHint') }}
          </p>
        </div>

        <div>
          <div class="row-between">
            <label>{{ t('foods.portions') }}</label>
            <button class="secondary small-btn" type="button" @click="addPortion">
              {{ t('foods.addPortion') }}
            </button>
          </div>
          <p class="muted small hint">
            {{ t('foods.portionsHint') }}
          </p>

          <div v-for="(portion, index) in portions" :key="index" class="row portion-row">
            <input v-model="portion.label" type="text" :placeholder="t('foods.portionLabelPlaceholder')" />
            <input v-model.number="portion.grams" type="number" min="1" :placeholder="t('foods.portionGramsPlaceholder')" />
            <button class="ghost" type="button" @click="removePortion(index)">✕</button>
          </div>
        </div>

        <p v-if="foodError" class="alert alert-error">{{ foodError }}</p>
        <p v-if="foodSuccess" class="alert alert-success">{{ foodSuccess }}</p>
        <p v-for="warning in foodWarnings" :key="warning" class="alert alert-info small">
          {{ warning }}
        </p>

        <button type="submit" :disabled="savingFood">
          {{ savingFood ? t('common.saving') : t('foods.saveFood') }}
        </button>
      </form>
    </div>

    <!-- ---------- New recipe ---------- -->
    <template v-else>
      <div class="card">
        <h2>{{ t('foods.buildRecipe') }}</h2>
        <p class="muted small note">
          {{ t('foods.recipeIntro') }}
        </p>

        <form class="stack" @submit.prevent="saveRecipe">
          <div class="grid grid-2">
            <div>
              <label for="rname">{{ t('foods.recipeName') }}</label>
              <input id="rname" v-model="recipe.name" type="text" required />
            </div>
            <div>
              <label for="rservings">{{ t('foods.recipeServings') }}</label>
              <input id="rservings" v-model.number="recipe.servings" type="number" min="1" required />
            </div>
          </div>

          <div>
            <label for="rdesc">{{ t('foods.recipeNotes') }}</label>
            <textarea id="rdesc" v-model="recipe.description" rows="2"></textarea>
          </div>

          <div>
            <label for="ringredient">{{ t('foods.addIngredient') }}</label>
            <div class="row">
              <input
                id="ringredient"
                v-model="ingredientQuery"
                type="search"
                :placeholder="t('foods.searchYourFoods')"
                @keydown.enter.prevent="searchIngredient"
              />
              <button class="secondary" type="button" @click="searchIngredient">
                {{ t('common.search') }}
              </button>
            </div>

            <ul v-if="ingredientResults.length" class="results">
              <li v-for="item in ingredientResults" :key="item.id">
                <button class="result" type="button" @click="addIngredient(item)">
                  <span class="result-name">{{ item.label }}</span>
                  <span class="muted small">
                    {{ t('diary.perHundred', { kcal: n(item.per100.kcal) }) }}
                  </span>
                </button>
              </li>
            </ul>
          </div>

          <table v-if="ingredients.length">
            <thead>
              <tr>
                <th>{{ t('foods.ingredient') }}</th>
                <th class="num">{{ t('foods.gramsColumn') }}</th>
                <th class="num">{{ t('common.kcal') }}</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, index) in ingredients" :key="index">
                <td>{{ item.food.label }}</td>
                <td class="num">
                  <input v-model.number="item.grams" type="number" min="1" class="grams-input" />
                </td>
                <td class="num">{{ n((item.food.per100.kcal * item.grams) / 100) }}</td>
                <td class="num shrink">
                  <button class="ghost" type="button" @click="ingredients.splice(index, 1)">✕</button>
                </td>
              </tr>
            </tbody>
          </table>

          <p v-if="ingredients.length" class="preview">
            <strong>
              {{ t('diary.kcalPerServing', { kcal: n(recipeTotals.perServing.kcal) }) }}
            </strong>
            <span class="muted">
              {{
                t('diary.preview', {
                  grams: n(recipeTotals.perServing.grams),
                  protein: n(recipeTotals.perServing.proteinG),
                  carbs: n(recipeTotals.perServing.carbsG),
                  fat: n(recipeTotals.perServing.fatG),
                })
              }}
              <br />
              {{
                t('foods.wholeRecipe', {
                  kcal: n(recipeTotals.kcal),
                  grams: n(recipeTotals.grams),
                })
              }}
            </span>
          </p>

          <label class="checkbox">
            <input v-model="recipe.public" type="checkbox" />
            {{ t('foods.sharePublicly') }}
          </label>

          <p v-if="recipeError" class="alert alert-error">{{ recipeError }}</p>
          <p v-if="recipeSuccess" class="alert alert-success">{{ recipeSuccess }}</p>

          <button type="submit">{{ t('foods.saveRecipe') }}</button>
        </form>
      </div>

      <div v-if="recipes.length" class="card recipes-card">
        <h2>{{ t('foods.yourRecipes') }}</h2>
        <table>
          <thead>
            <tr>
              <th>{{ t('foods.recipeName') }}</th>
              <th class="num">{{ t('foods.servingsColumn') }}</th>
              <th class="num">{{ t('foods.kcalPerServingColumn') }}</th>
              <!-- The figure that falls out of entering every ingredient by
                   weight, and the one that makes logging a recipe by weight
                   possible. Worth showing next to the per-serving one. -->
              <th class="num">{{ t('foods.kcalPerHundredColumn') }}</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in recipes" :key="item.id">
              <td>{{ item.name }}</td>
              <td class="num">{{ item.servings }}</td>
              <td class="num">{{ n(item.perServing.kcal) }}</td>
              <td class="num muted">{{ n(item.per100.kcal) }}</td>
              <td class="num shrink">
                <button class="ghost" type="button" @click="deleteRecipe(item.id)">✕</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<style scoped>
.tabs { display: flex; gap: 8px; margin-bottom: 16px; }
.note { margin: 0 0 16px; }
.hint { margin: 5px 0 0; }
.small-btn { padding: 4px 10px; font-size: 13px; }

.portion-row { margin-top: 8px; align-items: center; }
.portion-row input:first-child { flex: 2; }
.portion-row input:nth-child(2) { flex: 1; }

.results { list-style: none; margin: 10px 0 0; padding: 0; display: flex; flex-direction: column; gap: 4px; }

.result {
  width: 100%;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  text-align: start;
  background: var(--surface-2);
  color: var(--text);
  border: 1px solid transparent;
  padding: 9px 12px;
}

.result:hover { border-color: var(--accent); }
.result-name { font-weight: 600; }

.preview {
  background: var(--surface-2);
  border-radius: var(--radius-sm);
  padding: 12px;
  font-size: 14px;
  line-height: 1.7;
}

.grams-input { width: 90px; text-align: end; }
.shrink { width: 1%; }

.checkbox { display: flex; align-items: center; gap: 8px; font-size: 14px; color: var(--text); font-weight: 400; }
.checkbox input { width: auto; }

.recipes-card { margin-top: 16px; }
</style>
