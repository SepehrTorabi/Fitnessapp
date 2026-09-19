<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { api } from '@/api/client'
import { useApiMessage } from '@/composables/useApiMessage'
import { useNumbers } from '@/composables/useNumbers'
import type { Exercise, ExercisePurpose } from '@/api/types'
import { LOCALE_ENDONYMS } from '@/i18n'
import VideoPlayer from '@/components/VideoPlayer.vue'
import TranslationEditor from '@/components/TranslationEditor.vue'
import { DsBadge } from '@/design-system/components'

/**
 * The shared exercise catalogue, as a trainer sees it.
 *
 * Only rendered for somebody who may actually change it - everyone else meets
 * the same exercises as suggestions, with no editing anywhere in sight. The
 * server enforces that independently; this is about not offering a control that
 * would answer 403.
 *
 * The form is deliberately plain and always visible rather than hidden behind a
 * modal. Defining exercises is a curation task done several at a time, and a
 * dialog that has to be reopened for each one turns a five-minute job into a
 * tedious one.
 */
const props = defineProps<{ exercises: Exercise[] }>()

const emit = defineEmits<{ changed: [] }>()

const { t } = useI18n()
const apiMessage = useApiMessage()
const { decimal } = useNumbers()

const PURPOSES: ExercisePurpose[] = [
  'build_muscle',
  'fat_burning',
  'endurance',
  'strength',
  'mobility',
  'general_fitness',
]

/** Null while adding, an id while correcting one that exists. */
const editingId = ref<number | null>(null)
const open = ref(false)

const name = ref('')
const kcalPerMinute = ref<number | null>(null)
const purposes = ref<ExercisePurpose[]>([])
const description = ref('')

const busy = ref(false)
const error = ref('')
const violations = ref<Record<string, string>>({})

/**
 * The clip, which does not travel with the rest of the form.
 *
 * An exercise is created or corrected as JSON and its video is uploaded
 * separately, because a video is megabytes and does not belong inside a JSON
 * body. For the person filling the form that is invisible: they pick a file,
 * press Save once, and the two requests happen in order - the exercise first,
 * because a new one has no id to attach anything to until it exists.
 */
const videoFile = ref<File | null>(null)
const videoInput = ref<HTMLInputElement | null>(null)
const uploading = ref(false)

/** The clip already attached to the exercise being edited, if any. */
const existingVideo = ref<string | null>(null)
const existingVideoType = ref<string | null>(null)
/** Set when the trainer asked to drop the existing clip without replacing it. */
const removeVideo = ref(false)

/**
 * What the player is showing, or null when it is shut.
 *
 * Just the three things the player needs, rather than an Exercise: the clip
 * being previewed is sometimes one already attached to a row and sometimes the
 * one on the half-filled form above, and the form is not an exercise yet.
 */
interface Clip {
  src: string
  mimeType: string | null
  title: string
}

const previewing = ref<Clip | null>(null)

/** The editor form, so opening it can bring it into view. */
const editor = ref<HTMLElement | null>(null)
const firstField = ref<HTMLInputElement | null>(null)

/**
 * Bring the form to the person rather than making them find it.
 *
 * The catalogue is a long table and the editor opens above it, so pressing the
 * pencil on the fortieth row used to scroll nothing: the form appeared far off
 * the top of the screen and looked as though the button had done nothing at all.
 *
 * Focus goes to the first field as well as the scroll. That is what makes it
 * work for a keyboard - the caret is where typing should start - and it is also
 * what tells a screen reader that something happened, which a scroll on its own
 * does not.
 */
async function revealEditor(): Promise<void> {
  await nextTick()

  editor.value?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  firstField.value?.focus({ preventScroll: true })
}

function previewExisting(): void {
  if (!existingVideo.value) return

  previewing.value = {
    src: existingVideo.value,
    mimeType: existingVideoType.value,
    title: name.value,
  }
}

function previewExercise(exercise: Exercise): void {
  if (!exercise.videoUrl) return

  previewing.value = {
    src: exercise.videoUrl,
    mimeType: exercise.videoMimeType,
    title: exercise.name,
  }
}

const ACCEPTED = 'video/mp4,video/webm,video/quicktime'

function onVideoChosen(event: Event): void {
  const input = event.target as HTMLInputElement

  videoFile.value = input.files?.[0] ?? null
  // Choosing a replacement is itself a decision not to keep the old one.
  if (videoFile.value) removeVideo.value = false
}

function clearChosenVideo(): void {
  videoFile.value = null
  if (videoInput.value) videoInput.value.value = ''
}

const heading = computed(() =>
  editingId.value === null ? t('activity.newExercise') : t('common.edit'),
)

function startNew(): void {
  editingId.value = null
  name.value = ''
  kcalPerMinute.value = null
  purposes.value = []
  description.value = ''
  error.value = ''
  violations.value = {}
  resetVideoState(null)
  open.value = true
  void revealEditor()
}

function resetVideoState(exercise: Exercise | null): void {
  clearChosenVideo()
  removeVideo.value = false
  existingVideo.value = exercise?.videoUrl ?? null
  existingVideoType.value = exercise?.videoMimeType ?? null
}

function startEdit(exercise: Exercise): void {
  editingId.value = exercise.id
  name.value = exercise.name
  kcalPerMinute.value = exercise.kcalPerMinute
  // Copied, not referenced: abandoning the form must not have edited the row
  // behind it.
  purposes.value = [...exercise.purposes]
  description.value = exercise.description ?? ''
  error.value = ''
  violations.value = {}
  resetVideoState(exercise)
  open.value = true
  void revealEditor()
}

function cancel(): void {
  open.value = false
  editingId.value = null
}

function togglePurpose(purpose: ExercisePurpose): void {
  purposes.value = purposes.value.includes(purpose)
    ? purposes.value.filter((p) => p !== purpose)
    : [...purposes.value, purpose]
}

async function save(): Promise<void> {
  busy.value = true
  error.value = ''
  violations.value = {}

  const payload = {
    name: name.value,
    kcalPerMinute: kcalPerMinute.value ?? 0,
    purposes: purposes.value,
    description: description.value.trim() || null,
  }

  try {
    // The exercise first: a new one has no id for the upload to attach to.
    const saved =
      editingId.value === null
        ? (await api.createExercise(payload)).exercise
        : (await api.updateExercise(editingId.value, payload)).exercise

    if (videoFile.value) {
      uploading.value = true
      await api.uploadExerciseVideo(saved.id, videoFile.value)
    } else if (removeVideo.value) {
      await api.deleteExerciseVideo(saved.id)
    }

    open.value = false
    editingId.value = null
    resetVideoState(null)
    emit('changed')
  } catch (e) {
    error.value = apiMessage(e, 'activity.exerciseSaveFailed')

    const violationsOf = (e as { violations?: Record<string, string> }).violations
    if (violationsOf) violations.value = violationsOf
  } finally {
    busy.value = false
    uploading.value = false
  }
}

async function remove(exercise: Exercise): Promise<void> {
  // A browser confirm rather than a designed dialog: removing an exercise is
  // rare, reversible in effect (logged activities survive) and not worth a
  // component of its own.
  if (!window.confirm(t('activity.confirmDelete', { name: exercise.name }))) return

  error.value = ''

  try {
    await api.deleteExercise(exercise.id)
    emit('changed')
  } catch (e) {
    error.value = apiMessage(e, 'activity.exerciseDeleteFailed')
  }
}
</script>

<template>
  <section class="card">
    <div class="row-between head">
      <div>
        <h3>{{ t('activity.catalogueTitle') }}</h3>
        <p class="muted small intro">{{ t('activity.catalogueIntro') }}</p>
      </div>

      <button v-if="!open" type="button" @click="startNew">{{ t('activity.newExercise') }}</button>
    </div>

    <p v-if="error" class="alert alert-error small">{{ error }}</p>

    <form v-if="open" ref="editor" class="editor" @submit.prevent="save">
      <h4 class="editor-heading">{{ heading }}</h4>

      <div class="row">
        <div class="field grow">
          <label for="ex-name">{{ t('activity.exerciseName') }}</label>
          <input
            id="ex-name"
            ref="firstField"
            v-model="name"
            type="text"
            required
            :aria-invalid="Boolean(violations.name)"
          />
          <p v-if="violations.name" class="field-error">{{ violations.name }}</p>
        </div>

        <div class="field">
          <label for="ex-rate">{{ t('activity.exerciseRate') }}</label>
          <input
            id="ex-rate"
            v-model.number="kcalPerMinute"
            type="number"
            min="0.1"
            step="0.1"
            required
            :aria-invalid="Boolean(violations.kcalPerMinute)"
          />
          <p v-if="violations.kcalPerMinute" class="field-error">{{ violations.kcalPerMinute }}</p>
        </div>
      </div>

      <p class="muted small hint">{{ t('activity.exerciseRateHint') }}</p>

      <fieldset class="purposes">
        <legend>{{ t('activity.exercisePurposes') }}</legend>

        <div class="purpose-options">
          <label v-for="purpose in PURPOSES" :key="purpose" class="purpose">
            <input
              type="checkbox"
              :checked="purposes.includes(purpose)"
              @change="togglePurpose(purpose)"
            />
            <span>{{ t(`purpose.${purpose}`) }}</span>
          </label>
        </div>

        <p class="muted small hint">{{ t('activity.exercisePurposesHint') }}</p>
        <p v-if="violations.purposes" class="field-error">{{ violations.purposes }}</p>
      </fieldset>

      <div class="field">
        <label for="ex-notes">{{ t('activity.exerciseDescription') }}</label>
        <textarea id="ex-notes" v-model="description" rows="2"></textarea>
      </div>

      <!-- ----- The demonstration clip ----- -->
      <div class="field video-field">
        <label for="ex-video">{{ t('activity.exerciseVideo') }}</label>

        <!-- The existing clip, if there is one, with a way to watch it before
             deciding to replace it. -->
        <p v-if="existingVideo && !videoFile && !removeVideo" class="current">
          <button type="button" class="ghost link" @click="previewExisting">
            ▶ {{ t('activity.videoCurrent') }}
          </button>
          <button type="button" class="ghost link danger" @click="removeVideo = true">
            {{ t('activity.videoRemove') }}
          </button>
        </p>

        <p v-if="removeVideo" class="muted small">
          {{ t('activity.videoWillBeRemoved') }}
          <button type="button" class="ghost link" @click="removeVideo = false">
            {{ t('common.cancel') }}
          </button>
        </p>

        <input
          id="ex-video"
          ref="videoInput"
          type="file"
          :accept="ACCEPTED"
          @change="onVideoChosen"
        />

        <p v-if="videoFile" class="muted small chosen">
          {{ videoFile.name }}
          <button type="button" class="ghost link" @click="clearChosenVideo">
            {{ t('common.remove') }}
          </button>
        </p>

        <p class="muted small hint">{{ t('activity.exerciseVideoHint') }}</p>
      </div>

      <div class="row">
        <button type="submit" :disabled="busy || purposes.length === 0">
          {{ uploading ? t('activity.videoUploading') : busy ? t('common.saving') : t('common.save') }}
        </button>
        <button class="secondary" type="button" @click="cancel">{{ t('common.cancel') }}</button>
      </div>
    </form>

    <p v-if="props.exercises.length === 0" class="empty">{{ t('activity.noExercises') }}</p>

    <table v-else class="catalogue">
      <tbody>
        <template v-for="exercise in props.exercises" :key="exercise.id">
        <tr>
          <td>
            <strong>{{ exercise.name }}</strong>
            <!-- Said out loud when the name on screen is the fallback rather
                 than this reader's language, so an English name in a German
                 interface reads as "not translated yet" instead of as a
                 mistake. -->
            <span v-if="!exercise.translated" class="muted small block fallback">
              {{ t('translations.shownIn', { language: LOCALE_ENDONYMS[exercise.sourceLocale] }) }}
            </span>
            <span class="muted small block">
              {{
                exercise.createdBy
                  ? t('activity.definedBy', { name: exercise.createdBy })
                  : t('activity.definedBySeed')
              }}
            </span>
            <div class="badges">
              <DsBadge
                v-for="purpose in exercise.purposes"
                :key="purpose"
                :label="t(`purpose.${purpose}`)"
              />
            </div>
          </td>
          <td class="num rate">
            {{ t('activity.perMinute', { kcal: decimal(exercise.kcalPerMinute) }) }}
            <button
              v-if="exercise.videoUrl"
              type="button"
              class="ghost link has-video"
              :aria-label="`${t('activity.howTo')}: ${exercise.name}`"
              @click="previewExercise(exercise)"
            >
              ▶ {{ t('activity.videoLabel') }}
            </button>
          </td>
          <td class="num shrink">
            <div class="actions">
              <button
                class="ghost"
                type="button"
                :aria-label="`${t('common.edit')}: ${exercise.name}`"
                @click="startEdit(exercise)"
              >
                ✎
              </button>
              <button
                class="ghost"
                type="button"
                :aria-label="`${t('common.remove')}: ${exercise.name}`"
                @click="remove(exercise)"
              >
                ✕
              </button>
            </div>
          </td>
        </tr>
        <!-- Its own row rather than a control inside the name cell: the editor
             is a form, and opening one inside a cell shoves the whole column
             sideways. This component only renders for trainers, who are exactly
             the people allowed to translate the catalogue. -->
        <tr class="language-row">
          <td colspan="3">
            <TranslationEditor
              kind="exercise"
              :id="exercise.id"
              :source-locale="exercise.sourceLocale"
              :translations="exercise.translations"
              :can-edit="true"
              @updated="emit('changed')"
            />
          </td>
        </tr>
        </template>
      </tbody>
    </table>
    <VideoPlayer
      :open="previewing !== null"
      :src="previewing?.src ?? ''"
      :mime-type="previewing?.mimeType"
      :title="previewing?.title ?? ''"
      @close="previewing = null"
    />
  </section>
</template>

<style scoped>
.head { align-items: flex-start; }
.intro { margin: 0; max-width: 60ch; }

.editor {
  margin: var(--spacing-medium) 0;
  padding: var(--spacing-medium);
  background: var(--surface-subtle);
  border-radius: var(--border-radius-small);
}

.editor-heading { margin: 0 0 var(--spacing-small); font-size: var(--fontsize-body-large); }

.field { min-width: 140px; }
.grow { flex: 1; }
.hint { margin: var(--spacing-3xs) 0 var(--spacing-small); }

.purposes {
  border: none;
  padding: 0;
  margin: 0 0 var(--spacing-small);
}

.purposes legend {
  padding: 0;
  font-size: var(--fontsize-body-small);
  font-weight: var(--type-font-weight-semi-bold);
  color: var(--text-body);
}

.purpose-options {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-2xs) var(--spacing-medium);
  margin-top: var(--spacing-3xs);
}

.purpose {
  display: inline-flex;
  align-items: center;
  gap: var(--spacing-3xs);
  margin: 0;
  font-weight: var(--type-font-weight-regular, 400);
}

.purpose input { width: auto; }

.video-field { margin-bottom: var(--spacing-small); }
.video-field input[type='file'] { padding: var(--spacing-3xs) 0; border: none; background: none; }

.current { display: flex; flex-wrap: wrap; gap: var(--spacing-small); margin: 0 0 var(--spacing-3xs); }
.chosen { margin: var(--spacing-3xs) 0 0; }

.link {
  padding: 0;
  font: inherit;
  font-size: var(--fontsize-body-small);
  color: var(--text-action);
  border: none;
  background: none;
}

.link:hover { text-decoration: underline; }
.danger { color: var(--text-error); }
.danger:hover { color: var(--text-error); }

.has-video { display: block; margin-top: 2px; }

.catalogue { margin-top: var(--spacing-small); }
.badges { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
.rate { white-space: nowrap; font-variant-numeric: tabular-nums; }
.shrink { width: 1%; white-space: nowrap; }
.actions { display: flex; gap: 2px; justify-content: flex-end; }
.block { display: block; }
</style>
