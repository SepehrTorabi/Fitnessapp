<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '@/api/client'
import { useApiMessage } from '@/composables/useApiMessage'
import { useNumbers } from '@/composables/useNumbers'
import type { ActivityEntry } from '@/api/types'

/**
 * A day's activities, in the same shape as a day's entries.
 *
 * The sibling of {@see DiaryEntryTable}, and deliberately built to match it
 * column for column. An activity and a diary entry are the same kind of thing
 * to the person reading them - something I did on this day, worth this many
 * calories - and they sit one above the other on the dashboard. They used to be
 * drawn quite differently: the entries as a table with headers and controls,
 * the activities as an unlabelled two-column strip that could be deleted on one
 * screen and not on the other. Two presentations of one idea is the thing that
 * makes an interface feel arbitrary.
 *
 * So: the same header row, the same alignment, the same delete control in every
 * place the object appears. There is no inline editor here yet, because unlike
 * an entry's amount there is no one obvious field to correct - the calories of
 * a catalogue session are derived from its minutes, and of a free-text one are
 * typed. Deleting and re-adding is two clicks and is honest about what it does.
 */
const props = defineProps<{ activities: ActivityEntry[] }>()

const emit = defineEmits<{ changed: [] }>()

const { t } = useI18n()
const apiMessage = useApiMessage()
const { n } = useNumbers()

const error = ref('')
const busy = ref(false)

async function remove(activity: ActivityEntry): Promise<void> {
  error.value = ''
  busy.value = true

  try {
    await api.deleteActivity(activity.id)
    emit('changed')
  } catch (e) {
    error.value = apiMessage(e, 'activity.removeFailed')
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div>
    <p v-if="error" class="alert alert-error small activity-error">{{ error }}</p>

    <table>
      <thead>
        <tr>
          <th scope="col">{{ t('activity.tableWhat') }}</th>
          <th class="num" scope="col">{{ t('activity.tableMinutes') }}</th>
          <th class="num" scope="col">{{ t('common.kcal') }}</th>
          <th class="shrink"><span class="visually-hidden">{{ t('common.remove') }}</span></th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="activity in props.activities" :key="activity.id">
          <td>{{ activity.description }}</td>
          <td class="num muted">
            {{ activity.durationMinutes ? n(activity.durationMinutes) : t('common.none') }}
          </td>
          <td class="num">{{ n(activity.caloriesBurned) }}</td>
          <td class="shrink">
            <!-- The flex wrapper is inside the cell, not on it: a <td> set to
                 display:flex stops being a table cell and the column loses its
                 width. Same reason as in DiaryEntryTable. -->
            <div class="actions">
              <button
                class="ghost"
                type="button"
                :disabled="busy"
                :title="t('common.remove')"
                :aria-label="`${t('common.remove')}: ${activity.description}`"
                @click="remove(activity)"
              >
                ✕
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<style scoped>
.activity-error { margin: 0 0 var(--spacing-2xs); }

.actions { display: flex; gap: var(--spacing-3xs); justify-content: flex-end; }
</style>
