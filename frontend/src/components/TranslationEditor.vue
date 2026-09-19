<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '@/api/client'
import { useApiMessage } from '@/composables/useApiMessage'
import { LOCALE_ENDONYMS, SUPPORTED_LOCALES } from '@/i18n'
import type { AppLocale, Exercise, Food, Recipe } from '@/api/types'

/**
 * Add, change and remove the language versions of one catalogue entry.
 *
 * One component for foods, recipes and exercises, because from here they are
 * the same thing: a name and one more field, per language. The `kind` prop
 * decides which endpoint is called and what the second field is called - a
 * brand for a food, a description for the other two.
 *
 * Collapsed by default, and deliberately so. Most people use the app in one
 * language and never open this; it should cost them one line of screen, not a
 * form they have to scroll past every time they add a food.
 *
 * The source language is listed but not editable here. Editing it would mean
 * two places that can change the same text, and the second one always ends up
 * being the one nobody remembers to update - so that field stays where it is,
 * on the entry's own form.
 */
const props = defineProps<{
  kind: 'food' | 'recipe' | 'exercise'
  id: number
  sourceLocale: AppLocale
  translations: Partial<Record<AppLocale, { name: string; brand?: string | null; description?: string | null }>>
  /** False hides every control and leaves the summary readable. */
  canEdit?: boolean
}>()

const emit = defineEmits<{
  (e: 'updated', entity: Food | Recipe | Exercise): void
}>()

const { t } = useI18n()
const apiMessage = useApiMessage()

const open = ref(false)
const busy = ref<AppLocale | null>(null)
const error = ref('')

/** Everything except the language the entry was written in. */
const targets = computed(() => SUPPORTED_LOCALES.filter((code) => code !== props.sourceLocale))

/** The second field is a brand for foods and a description for the rest. */
const secondIsBrand = computed(() => props.kind === 'food')

const secondLabel = computed(() =>
  secondIsBrand.value ? t('translations.brandField') : t('translations.descriptionField'),
)

const filledCount = computed(() => targets.value.filter((code) => props.translations[code]).length)

/**
 * The working copy the inputs are bound to.
 *
 * Kept separate from the prop so typing does not mutate what the parent handed
 * down, and so an unsaved edit survives the panel being collapsed and reopened.
 */
const draft = reactive<Record<string, { name: string; second: string }>>({})

function syncDraft(): void {
  for (const code of SUPPORTED_LOCALES) {
    const existing = props.translations[code]

    draft[code] = {
      name: existing?.name ?? '',
      second: (secondIsBrand.value ? existing?.brand : existing?.description) ?? '',
    }
  }
}

syncDraft()

// Re-read whenever the parent sends a new version of the entry - after a save,
// or after the list is reloaded.
watch(() => props.translations, syncDraft, { deep: true })

async function save(locale: AppLocale): Promise<void> {
  const row = draft[locale]

  if (!row || row.name.trim() === '') {
    error.value = t('translations.nameRequired')
    return
  }

  error.value = ''
  busy.value = locale

  try {
    // An empty second field is sent as null rather than as "", so the reader
    // falls back to the source text instead of being shown a blank.
    const second = row.second.trim() === '' ? null : row.second.trim()
    const name = row.name.trim()

    if (props.kind === 'food') {
      emit('updated', (await api.putFoodTranslation(props.id, locale, { name, brand: second })).food)
    } else if (props.kind === 'recipe') {
      emit(
        'updated',
        (await api.putRecipeTranslation(props.id, locale, { name, description: second })).recipe,
      )
    } else {
      emit(
        'updated',
        (await api.putExerciseTranslation(props.id, locale, { name, description: second })).exercise,
      )
    }
  } catch (e) {
    error.value = apiMessage(e, 'translations.saveFailed')
  } finally {
    busy.value = null
  }
}

async function remove(locale: AppLocale): Promise<void> {
  error.value = ''
  busy.value = locale

  try {
    if (props.kind === 'food') {
      emit('updated', (await api.deleteFoodTranslation(props.id, locale)).food)
    } else if (props.kind === 'recipe') {
      emit('updated', (await api.deleteRecipeTranslation(props.id, locale)).recipe)
    } else {
      emit('updated', (await api.deleteExerciseTranslation(props.id, locale)).exercise)
    }
  } catch (e) {
    error.value = apiMessage(e, 'translations.removeFailed')
  } finally {
    busy.value = null
  }
}
</script>

<template>
  <div class="translations">
    <button type="button" class="toggle" :aria-expanded="open" @click="open = !open">
      <span class="globe" aria-hidden="true">🌐</span>
      {{ t('translations.title') }}
      <span class="count">{{ filledCount }}/{{ targets.length }}</span>
    </button>

    <div v-if="open" class="panel">
      <p class="muted small note">
        {{ t('translations.writtenIn', { language: LOCALE_ENDONYMS[props.sourceLocale] }) }}
      </p>

      <p v-if="!canEdit" class="muted small note">{{ t('translations.readOnly') }}</p>

      <div v-for="code in targets" :key="code" class="language-block">
        <h4 class="language">{{ LOCALE_ENDONYMS[code] }}</h4>

        <div>
          <label :for="`tr-name-${props.kind}-${props.id}-${code}`">{{ t('translations.nameField') }}</label>
          <input
            :id="`tr-name-${props.kind}-${props.id}-${code}`"
            v-model="draft[code]!.name"
            type="text"
            :disabled="!canEdit || busy !== null"
          />
        </div>

        <div>
          <label :for="`tr-second-${props.kind}-${props.id}-${code}`">
            {{ secondLabel }} <span class="muted">({{ t('common.optional') }})</span>
          </label>
          <!-- A description is prose and a brand is a word, so they do not get
               the same control. -->
          <textarea
            v-if="!secondIsBrand"
            :id="`tr-second-${props.kind}-${props.id}-${code}`"
            v-model="draft[code]!.second"
            rows="2"
            :disabled="!canEdit || busy !== null"
          ></textarea>
          <input
            v-else
            :id="`tr-second-${props.kind}-${props.id}-${code}`"
            v-model="draft[code]!.second"
            type="text"
            :disabled="!canEdit || busy !== null"
          />
        </div>

        <div v-if="canEdit" class="actions">
          <button type="button" :disabled="busy !== null" @click="save(code)">
            {{ busy === code ? t('common.saving') : t('common.save') }}
          </button>
          <button
            v-if="props.translations[code]"
            type="button"
            class="secondary"
            :disabled="busy !== null"
            @click="remove(code)"
          >
            {{ t('common.remove') }}
          </button>
        </div>
      </div>

      <p v-if="error" class="alert alert-error">{{ error }}</p>
    </div>
  </div>
</template>

<style scoped>
.translations { margin-top: var(--spacing-2xs); }

.toggle {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-3xs);
  padding: var(--spacing-3xs) var(--spacing-2xs);
  background: transparent;
  border-color: var(--border-primary);
  color: var(--text-body);
  font-size: var(--fontsize-body-small);
}

.toggle:hover { border-color: var(--accent); }

.globe { line-height: 1; }

.count {
  padding: 0 6px;
  border-radius: 999px;
  background: var(--surface-subtle);
  font-variant-numeric: tabular-nums;
}

.panel {
  margin-top: var(--spacing-2xs);
  padding: var(--spacing-small);
  border: var(--border-width-small) solid var(--border-primary);
  border-radius: var(--border-radius-medium);
  background: var(--surface-subtle);
}

.note { margin: 0 0 var(--spacing-2xs); }

/*
 * Named language-block rather than row: `.row` is a global utility in main.css
 * that lays its children out horizontally and centres them, and a scoped style
 * does not stop a global class rule from applying to the same element. The two
 * fought, and the centring won.
 */
.language-block {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3xs);
  padding: var(--spacing-2xs) 0;
  border-top: var(--border-width-small) solid var(--border-primary);
}

.language-block:first-of-type { border-top: none; }

.language { margin: 0; font-size: var(--fontsize-body-medium); }

.actions { display: flex; gap: var(--spacing-3xs); }
.actions button { width: auto; }
</style>
