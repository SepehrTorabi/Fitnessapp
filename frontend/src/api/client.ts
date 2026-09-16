import type {
  ActivityDay,
  ActivityEntry,
  AdminUser,
  AppLocale,
  CalendarPreference,
  DashboardData,
  DayView,
  DiaryEntry,
  Exercise,
  ExercisePurpose,
  ExerciseSuggestions,
  Food,
  FoodSearchResult,
  Recipe,
  Role,
  ThemePreference,
  User,
} from './types'

/**
 * The one place that talks to the API.
 *
 * Every response goes through `request`, so authentication, the error envelope
 * and JSON handling are dealt with once rather than in every component.
 */

/** An error the API reported, carrying enough detail for the UI to react. */
export class ApiError extends Error {
  readonly status: number
  readonly code: string
  /** Field-level violations, when the API rejected a payload (422). */
  readonly violations: Record<string, string>

  // Written out rather than declared as constructor parameter properties: the
  // build runs with erasableSyntaxOnly, which only permits TypeScript syntax
  // that disappears entirely at compile time.
  constructor(
    status: number,
    code: string,
    message: string,
    violations: Record<string, string> = {},
  ) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
    this.violations = violations
  }

  get isUnauthenticated(): boolean {
    return this.status === 401
  }

  get isValidationError(): boolean {
    return this.status === 422
  }
}

type Query = Record<string, string | number | undefined>

function withQuery(path: string, query?: Query): string {
  if (!query) return path

  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(query)) {
    if (value !== undefined && value !== '') params.set(key, String(value))
  }

  const qs = params.toString()
  return qs ? `${path}?${qs}` : path
}

/**
 * Symfony answers a failed #[MapRequestPayload] with an RFC 7807 document whose
 * `violations` array names the offending fields. Flattening it to
 * { fieldName: message } is what the forms actually want.
 */
function extractViolations(body: unknown): Record<string, string> {
  const violations: Record<string, string> = {}

  if (typeof body !== 'object' || body === null) return violations

  const list = (body as { violations?: unknown }).violations
  if (!Array.isArray(list)) return violations

  for (const violation of list) {
    if (typeof violation !== 'object' || violation === null) continue

    const { propertyPath, title, message } = violation as Record<string, unknown>
    if (typeof propertyPath === 'string' && propertyPath !== '') {
      violations[propertyPath] = String(message ?? title ?? 'Invalid value.')
    }
  }

  return violations
}

async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
  const response = await fetch(path, {
    ...init,
    headers: {
      Accept: 'application/json',
      // Everything this client sends is JSON, except an upload. FormData must
      // be left alone: its Content-Type carries the multipart boundary, only
      // the browser knows what that boundary is, and stamping
      // "application/json" over it produces a body the server cannot parse at
      // all - which arrives as a puzzling "no file was sent".
      ...(init.body && !(init.body instanceof FormData)
        ? { 'Content-Type': 'application/json' }
        : {}),
      ...init.headers,
    },
    // Authentication is a session cookie, so it has to ride along. Without this
    // the browser omits it and every call comes back 401.
    credentials: 'same-origin',
  })

  if (response.status === 204) {
    return undefined as T
  }

  const text = await response.text()
  let body: unknown = null

  if (text) {
    try {
      body = JSON.parse(text)
    } catch {
      // A non-JSON body means something failed outside the API - a proxy error,
      // a PHP fatal. Keep the raw text for the message below.
      body = null
    }
  }

  if (!response.ok) {
    const envelope = (body ?? {}) as { error?: string; message?: string; detail?: string }

    throw new ApiError(
      response.status,
      envelope.error ?? 'http_error',
      envelope.message ?? envelope.detail ?? `Request failed with status ${response.status}.`,
      extractViolations(body),
    )
  }

  return body as T
}

const json = (payload: unknown): RequestInit => ({
  method: 'POST',
  body: JSON.stringify(payload),
})

export const api = {
  // --- Authentication ---
  // The locale is sent so the confirmation mail - written before the user has
  // signed in even once - arrives in the language the sign-up form was in.
  // The theme and calendar ride along too - not because the mail needs them,
  // but so the first signed-in screen looks like the sign-up form did.
  register: (payload: {
    email: string
    password: string
    displayName: string
    locale?: AppLocale
    theme?: ThemePreference
    calendar?: CalendarPreference
  }) => request<{ message: string; user: User }>('/api/auth/register', json(payload)),

  verifyEmail: (token: string) =>
    request<{ message: string; user: User }>('/api/auth/verify-email', json({ token })),

  resendVerification: (email: string) =>
    request<{ message: string }>('/api/auth/resend-verification', json({ email })),

  login: (email: string, password: string) =>
    request<{ user: User }>('/api/auth/login', json({ email, password })),

  logout: () => request<{ message: string }>('/api/auth/logout', { method: 'POST' }),

  // --- Account ---
  me: () => request<{ user: User }>('/api/me'),

  updateProfile: (payload: {
    birthDate: string
    sex: string
    heightCm: number
    activityLevel: string
    goal: string
  }) => request<{ user: User }>('/api/me/profile', { ...json(payload), method: 'PUT' }),

  addMeasurement: (payload: {
    weightKg: number
    muscleMassKg?: number | null
    fatMassKg?: number | null
    measuredOn?: string
  }) => request<{ user: User }>('/api/me/measurements', json(payload)),

  measurements: () => request<{ measurements: unknown[] }>('/api/me/measurements'),

  // Fields left out are left alone, so the settings screen can change the
  // language without also resending the theme.
  updatePreferences: (payload: {
    locale?: AppLocale
    theme?: ThemePreference
    calendar?: CalendarPreference
  }) => request<{ user: User }>('/api/me/preferences', { ...json(payload), method: 'PUT' }),

  // --- Dashboard ---
  dashboard: (date?: string, days = 7) =>
    request<DashboardData>(withQuery('/api/dashboard', { date, days })),

  // --- Foods ---
  searchFoods: (q: string) => request<FoodSearchResult>(withQuery('/api/foods', { q })),

  food: (id: number) => request<{ food: Food }>(`/api/foods/${id}`),

  foodByBarcode: (barcode: string) => request<{ food: Food }>(`/api/foods/barcode/${barcode}`),

  importFood: (externalId: string) =>
    request<{ food: Food }>(`/api/foods/import/${externalId}`, { method: 'POST' }),

  createFood: (payload: unknown) =>
    request<{ food: Food; warnings: string[] }>('/api/foods', json(payload)),

  // --- Diary ---
  day: (date?: string) => request<DayView>(withQuery('/api/diary', { date })),

  addEntry: (payload: {
    foodId?: number
    recipeId?: number
    mealType: string
    quantity: number
    unit: string
    portionLabel?: string | null
    loggedOn?: string
  }) => request<{ entry: DiaryEntry }>('/api/diary/entries', json(payload)),

  // A PATCH, so the inline editor can send only what the user actually
  // changed - correcting the amount must not also have to resend the meal.
  updateEntry: (
    id: number,
    payload: {
      quantity?: number
      unit?: string
      portionLabel?: string | null
      mealType?: string
      loggedOn?: string
    },
  ) =>
    request<{ entry: DiaryEntry }>(`/api/diary/entries/${id}`, {
      ...json(payload),
      method: 'PATCH',
    }),

  deleteEntry: (id: number) => request<void>(`/api/diary/entries/${id}`, { method: 'DELETE' }),

  // --- Activity ---
  // Their own endpoints rather than a corner of the diary: training is not a
  // kind of eating, and the API follows the page.
  activityDay: (date?: string) => request<ActivityDay>(withQuery('/api/activities', { date })),

  addActivity: (payload: {
    description: string
    caloriesBurned: number
    durationMinutes?: number | null
    performedOn?: string
  }) => request<{ activity: ActivityEntry }>('/api/activities', json(payload)),

  // The exercise's per-minute rate and the duration decide the calories, unless
  // caloriesBurned overrides them.
  addActivityFromExercise: (payload: {
    exerciseId: number
    durationMinutes: number
    caloriesBurned?: number | null
    performedOn?: string
  }) => request<{ activity: ActivityEntry }>('/api/activities/from-exercise', json(payload)),

  updateActivity: (
    id: number,
    payload: {
      description?: string
      caloriesBurned?: number
      durationMinutes?: number | null
      performedOn?: string
    },
  ) =>
    request<{ activity: ActivityEntry }>(`/api/activities/${id}`, {
      ...json(payload),
      method: 'PATCH',
    }),

  deleteActivity: (id: number) => request<void>(`/api/activities/${id}`, { method: 'DELETE' }),

  activitySuggestions: () => request<ExerciseSuggestions>('/api/activities/suggestions'),

  // --- Exercise catalogue ---
  // Readable by anyone signed in; the writes answer 403 unless the caller is a
  // trainer, which is why the interface hides them rather than relying on it.
  exercises: (q?: string) => request<{ exercises: Exercise[] }>(withQuery('/api/exercises', { q })),

  createExercise: (payload: {
    name: string
    kcalPerMinute: number
    purposes: ExercisePurpose[]
    description?: string | null
  }) => request<{ exercise: Exercise }>('/api/exercises', json(payload)),

  // A PUT, not a PATCH: an exercise is small enough that the form always sends
  // the whole thing, and "no purposes" has to be distinguishable from "field
  // omitted".
  updateExercise: (
    id: number,
    payload: {
      name: string
      kcalPerMinute: number
      purposes: ExercisePurpose[]
      description?: string | null
    },
  ) => request<{ exercise: Exercise }>(`/api/exercises/${id}`, { ...json(payload), method: 'PUT' }),

  deleteExercise: (id: number) => request<void>(`/api/exercises/${id}`, { method: 'DELETE' }),

  /**
   * Multipart, so the browser streams the file and shows its own progress -
   * and deliberately without a Content-Type header. The boundary is part of
   * that header and only the browser knows it; setting it by hand produces a
   * body the server cannot parse.
   */
  uploadExerciseVideo: (id: number, file: File) => {
    const body = new FormData()
    body.append('video', file)

    return request<{ exercise: Exercise }>(`/api/exercises/${id}/video`, { method: 'POST', body })
  },

  deleteExerciseVideo: (id: number) =>
    request<{ exercise: Exercise }>(`/api/exercises/${id}/video`, { method: 'DELETE' }),

  // --- Administration ---
  adminUsers: (q?: string) =>
    request<{ users: AdminUser[]; assignableRoles: Role[] }>(withQuery('/api/admin/users', { q })),

  setUserRoles: (id: number, roles: Role[]) =>
    request<{ user: AdminUser }>(`/api/admin/users/${id}/roles`, {
      ...json({ roles }),
      method: 'PUT',
    }),

  // --- Recipes ---
  recipes: (q?: string) => request<{ recipes: Recipe[] }>(withQuery('/api/recipes', { q })),

  createRecipe: (payload: {
    name: string
    servings: number
    description?: string | null
    public?: boolean
    ingredients: { foodId: number; grams: number }[]
  }) => request<{ recipe: Recipe }>('/api/recipes', json(payload)),

  deleteRecipe: (id: number) => request<void>(`/api/recipes/${id}`, { method: 'DELETE' }),
}
