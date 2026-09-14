/**
 * The shapes the Symfony API returns.
 *
 * Written by hand to mirror the presenters in src/Api/Presenter. Keeping them
 * as types rather than guessing at runtime means a rename on the backend shows
 * up as a compile error here instead of as `undefined` in the UI.
 */

export interface Nutrients {
  kcal: number
  proteinG: number
  carbsG: number
  fatG: number
  fiberG: number | null
  sugarG: number | null
  saltG: number | null
}

export type Sex = 'male' | 'female'

export type ActivityLevel =
  | 'sedentary'
  | 'lightly_active'
  | 'moderately_active'
  | 'very_active'
  | 'extra_active'

export type Goal = 'lose_weight' | 'maintain_weight' | 'gain_muscle'

export type MealType = 'breakfast' | 'lunch' | 'dinner' | 'snack'

export type AppLocale = 'en' | 'de'

export type ThemePreference = 'system' | 'light' | 'dark'

/**
 * Interface settings. Unlike `profile` this is never null - a user who has
 * never opened the settings screen still has an effective language and theme,
 * and the client should not have to know the defaults itself.
 */
export interface UserPreferences {
  locale: AppLocale
  theme: ThemePreference
}

export interface UserProfile {
  birthDate: string
  age: number
  sex: Sex
  heightCm: number
  activityLevel: ActivityLevel
  goal: Goal
}

export interface BodyMeasurement {
  id: number
  measuredOn: string
  weightKg: number
  muscleMassKg: number | null
  fatMassKg: number | null
  leanBodyMassKg: number | null
}

export interface CalorieTarget {
  bmr: number
  tdee: number
  targetKcal: number
  formula: string
  macros: Nutrients
}

export interface User {
  id: number
  email: string
  displayName: string
  verified: boolean
  createdAt: string
  /** Null until the user fills in the onboarding form. */
  profile: UserProfile | null
  latestMeasurement: BodyMeasurement | null
  /** Null while there is no profile or no weigh-in to compute it from. */
  dailyTarget: CalorieTarget | null
  preferences: UserPreferences
}

export interface AvailableUnit {
  unit: string
  label: string
  grams: number | null
}

export interface FoodPortion {
  id: number
  label: string
  grams: number
}

export interface Food {
  id: number
  name: string
  brand: string | null
  label: string
  barcode: string | null
  source: string
  densityGPerMl: number
  per100: Nutrients
  portions: FoodPortion[]
  availableUnits: AvailableUnit[]
}

/** A hit from the external database, not yet in our catalogue - so it has no id. */
export interface ExternalFood {
  name: string
  brand: string | null
  barcode: string | null
  label: string
  source: string
  externalId: string
  per100: Nutrients
  portions: Record<string, number>
}

export interface FoodSearchResult {
  query: string
  local: Food[]
  external: ExternalFood[]
}

export interface DiaryEntry {
  id: number
  loggedOn: string
  mealType: MealType
  label: string
  foodId: number | null
  recipeId: number | null
  quantity: number
  unit: string
  portionLabel: string | null
  grams: number
  nutrients: Nutrients
  createdAt: string
}

export interface ActivityEntry {
  id: number
  performedOn: string
  description: string
  durationMinutes: number | null
  caloriesBurned: number
  createdAt: string
}

export interface DaySummary {
  date: string
  consumed: Nutrients
  caloriesBurned: number
  target: CalorieTarget | null
  budgetKcal: number | null
  remainingKcal: number | null
  /** Null while there is no target to compare against. */
  withinBudget: boolean | null
}

export interface DashboardData {
  today: DaySummary
  history: DaySummary[]
  averages: {
    daysLogged: number
    kcal: number | null
    proteinG: number | null
    carbsG: number | null
    fatG: number | null
  }
  recentEntries: DiaryEntry[]
}

export interface DayView {
  summary: DaySummary
  entries: DiaryEntry[]
  activities: ActivityEntry[]
}

export interface RecipeIngredient {
  id: number
  foodId: number
  label: string
  grams: number
  nutrients: Nutrients
}

export interface Recipe {
  id: number
  name: string
  description: string | null
  servings: number
  public: boolean
  totalGrams: number
  gramsPerServing: number
  totalNutrients: Nutrients
  perServing: Nutrients
  ingredients: RecipeIngredient[]
  createdAt: string
}
