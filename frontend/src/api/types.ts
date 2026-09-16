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

export type Goal =
  | 'lose_weight'
  | 'lose_fat_slowly'
  | 'maintain_weight'
  | 'recomposition'
  | 'gain_muscle'
  | 'gain_weight'
  | 'endurance'
  | 'strength'

/**
 * What an exercise is good for. A trainer tags each exercise with one or more;
 * the user's goal decides which of them get suggested.
 */
export type ExercisePurpose =
  | 'build_muscle'
  | 'fat_burning'
  | 'endurance'
  | 'strength'
  | 'mobility'
  | 'general_fitness'

/**
 * Symfony role strings. Everyone has ROLE_USER; the other two are granted by a
 * user administrator.
 */
export type Role = 'ROLE_USER' | 'ROLE_TRAINER' | 'ROLE_USER_ADMIN'

export type MealType = 'breakfast' | 'lunch' | 'dinner' | 'snack'

export type AppLocale = 'en' | 'de' | 'fa'

export type ThemePreference = 'system' | 'light' | 'dark'

/**
 * Which calendar dates are read in. Independent of the language: Persian text
 * with Gregorian dates and German text with Shamsi dates are both real
 * requests, and stored dates never move either way.
 */
export type CalendarPreference = 'gregorian' | 'persian'

/**
 * Interface settings. Unlike `profile` this is never null - a user who has
 * never opened the settings screen still has an effective language, theme and
 * calendar, and the client should not have to know the defaults itself.
 */
export interface UserPreferences {
  locale: AppLocale
  theme: ThemePreference
  calendar: CalendarPreference
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
  /**
   * Used only to decide what to show. Every restricted endpoint checks the role
   * itself, so hiding a button is a courtesy rather than the security boundary.
   */
  roles: Role[]
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
  /**
   * False when the outside product database could not be reached. Distinct from
   * `external` being empty, which means it was asked and had nothing - telling
   * the two apart is what stops an outage reading as "this food does not exist".
   */
  externalAvailable: boolean
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
  /** Null for a free-text activity, and null again once an exercise is retired. */
  exerciseId: number | null
  createdAt: string
}

/** One kind of training from the shared catalogue. */
export interface Exercise {
  id: number
  name: string
  description: string | null
  purposes: ExercisePurpose[]
  kcalPerMinute: number
  /**
   * Where the demonstration clip is, or null when the exercise has none. Its
   * presence is what decides whether a play button is offered at all.
   */
  videoUrl: string | null
  videoMimeType: string | null
  /** The trainer's display name, or null for the seeded catalogue. */
  createdBy: string | null
  createdAt: string
  updatedAt: string
}

export interface ActivityDay {
  date: string
  activities: ActivityEntry[]
  caloriesBurned: number
}

export interface ExerciseSuggestions {
  /** Null when the user has no profile yet, so there is no goal to suggest for. */
  goal: Goal | null
  exercises: Exercise[]
}

/** The administration screen's view of an account: identity and roles only. */
export interface AdminUser {
  id: number
  email: string
  displayName: string
  verified: boolean
  roles: Role[]
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
  /**
   * Per 100 g of the finished dish. Available because every ingredient is
   * entered as a weight, and what makes logging a recipe by weight possible.
   */
  per100: Nutrients
  ingredients: RecipeIngredient[]
  createdAt: string
}
