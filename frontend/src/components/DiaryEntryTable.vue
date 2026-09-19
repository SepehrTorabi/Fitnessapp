<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '@/api/client'
import { useApiMessage } from '@/composables/useApiMessage'
import type { AvailableUnit, DiaryEntry, MealType } from '@/api/types'
import { useNumbers } from '@/composables/useNumbers'
import { DsBadge } from '@/design-system/components'

/**
 * A day's entries, correctable where they are read.
 *
 * Editing used to mean going to the diary page, finding the right day, deleting
 * the wrong entry and logging it again - four steps to fix a number the user is
 * already looking at. So the row itself opens into an editor.
 *
 * What can be changed is the amount and the meal, not the food: swapping the
 * food of an entry is the same thing as deleting it and logging the right one,
 * and the API says so too.
 *
 * The unit dropdown needs the food's own portion definitions ("slice" only
 * exists for things that have slices), which the entry does not carry - so the
 * food is fetched when the editor opens, and only then. Loading seven foods to
 * render a table nobody is editing would be a lot of requests for nothing.
 */
const props = defineProps<{ entries: DiaryEntry[] }>()

const emit = defineEmits<{ changed: [] }>()

const { t } = useI18n()
const apiMessage = useApiMessage()
const { n, decimal } = useNumbers()

const mealTypes: MealType[] = ['breakfast', 'lunch', 'dinner', 'snack']

const editingId = ref<number | null>(null)
const quantity = ref(0)
const unit = ref('g')
const mealType = ref<MealType>('breakfast')
const units = ref<AvailableUnit[]>([])
const busy = ref(false)
const error = ref('')

function isEditing(entry: DiaryEntry): boolean {
  return editingId.value === entry.id
}

async function edit(entry: DiaryEntry): Promise<void> {
  editingId.value = entry.id
  quantity.value = entry.quantity
  mealType.value = entry.mealType
  error.value = ''

  // A named portion is chosen by its label; everything else by the unit itself.
  unit.value = entry.portionLabel ?? entry.unit
  units.value = []

  if (entry.foodId === null) return

  try {
    units.value = (await api.food(entry.foodId)).food.availableUnits
  } catch {
    // The food may have been deleted since. The amount can still be corrected,
    // just not the unit - so the dropdown stays out rather than the row
    // refusing to open.
    units.value = []
  }
}

function cancel(): void {
  editingId.value = null
  error.value = ''
}

async function save(entry: DiaryEntry): Promise<void> {
  busy.value = true
  error.value = ''

  const chosen = units.value.find((option) => option.label === unit.value)

  try {
    await api.updateEntry(entry.id, {
      quantity: quantity.value,
      mealType: mealType.value,
      // Named portions go as the generic "portion" unit plus the label, which
      // is what the server resolves against that food's own definitions.
      ...(chosen
        ? {
            unit: chosen.unit,
            portionLabel: chosen.unit === 'portion' ? chosen.label : null,
          }
        : {}),
    })

    editingId.value = null
    emit('changed')
  } catch (e) {
    error.value = apiMessage(e, 'dashboard.entryUpdateFailed')
  } finally {
    busy.value = false
  }
}

async function remove(entry: DiaryEntry): Promise<void> {
  error.value = ''

  try {
    await api.deleteEntry(entry.id)
    emit('changed')
  } catch (e) {
    error.value = apiMessage(e, 'dashboard.entryRemoveFailed')
  }
}
</script>

<template>
  <div>
    <p v-if="error" class="alert alert-error small entry-error">{{ error }}</p>

    <table>
      <thead>
        <tr>
          <th scope="col">{{ t('entry.food') }}</th>
          <th scope="col">{{ t('entry.meal') }}</th>
          <th class="num" scope="col">{{ t('entry.amount') }}</th>
          <th class="num" scope="col">{{ t('common.kcal') }}</th>
          <th class="num" scope="col">{{ t('entry.macros') }}</th>
          <th class="shrink"><span class="visually-hidden">{{ t('common.edit') }}</span></th>
        </tr>
      </thead>

      <tbody>
        <template v-for="entry in props.entries" :key="entry.id">
          <tr v-if="!isEditing(entry)">
            <td>{{ entry.label }}</td>
            <td><DsBadge :label="t(`meal.${entry.mealType}`)" /></td>
            <td class="num">{{ decimal(entry.quantity) }} {{ entry.portionLabel ?? entry.unit }}</td>
            <td class="num">{{ n(entry.nutrients.kcal) }}</td>
            <td class="num muted">
              {{ n(entry.nutrients.proteinG) }} /
              {{ n(entry.nutrients.carbsG) }} /
              {{ n(entry.nutrients.fatG) }}
            </td>
            <td class="shrink">
              <!-- The flex container is a div inside the cell rather than the
                   cell itself: a <td> with display:flex stops being a table
                   cell, the column loses its width, and the buttons end up
                   drawn on top of the column before it. -->
              <div class="actions">
                <button
                  class="ghost"
                  type="button"
                  :title="t('entry.edit')"
                  :aria-label="`${t('entry.edit')}: ${entry.label}`"
                  @click="edit(entry)"
                >
                  ✎
                </button>
                <button
                  class="ghost"
                  type="button"
                  :title="t('common.remove')"
                  :aria-label="`${t('common.remove')}: ${entry.label}`"
                  @click="remove(entry)"
                >
                  ✕
                </button>
              </div>
            </td>
          </tr>

          <!-- The editor replaces the row rather than opening below it, so the
               numbers being corrected stay where the eye already is. -->
          <tr v-else class="editing">
            <td colspan="6">
              <form class="editor" @submit.prevent="save(entry)">
                <strong class="editor-name">{{ entry.label }}</strong>

                <div class="editor-field">
                  <label :for="`qty-${entry.id}`">{{ t('diary.amount') }}</label>
                  <input
                    :id="`qty-${entry.id}`"
                    v-model.number="quantity"
                    type="number"
                    min="0.1"
                    step="any"
                    required
                  />
                </div>

                <div v-if="units.length" class="editor-field">
                  <label :for="`unit-${entry.id}`">{{ t('diary.unit') }}</label>
                  <select :id="`unit-${entry.id}`" v-model="unit">
                    <option v-for="option in units" :key="option.label" :value="option.label">
                      {{ option.label }}
                    </option>
                  </select>
                </div>

                <div class="editor-field">
                  <label :for="`meal-${entry.id}`">{{ t('diary.meal') }}</label>
                  <select :id="`meal-${entry.id}`" v-model="mealType">
                    <option v-for="type in mealTypes" :key="type" :value="type">
                      {{ t(`meal.${type}`) }}
                    </option>
                  </select>
                </div>

                <div class="editor-actions">
                  <button type="submit" :disabled="busy">
                    {{ busy ? t('common.saving') : t('common.save') }}
                  </button>
                  <button class="secondary" type="button" @click="cancel">
                    {{ t('common.cancel') }}
                  </button>
                </div>
              </form>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.entry-error { margin: 0 0 var(--spacing-small); }

.shrink { width: 1%; white-space: nowrap; }
.actions { display: flex; gap: 2px; justify-content: flex-end; }

.editing { background: var(--surface-subtle); }

.editor {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: var(--spacing-small);
  padding: var(--spacing-2xs) 0;
}

.editor-name { flex-basis: 100%; }

.editor-field { flex: 1; min-width: 96px; }
.editor-field label { margin-bottom: 2px; }

.editor-actions { display: flex; gap: var(--spacing-2xs); }
</style>
