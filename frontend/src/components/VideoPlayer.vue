<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * A small player for one demonstration clip.
 *
 * Built on the browser's own `<video controls>` rather than a hand-rolled
 * transport. Play, pause, seek, volume, fullscreen and picture-in-picture all
 * come free, they are already keyboard accessible, and on a phone they are the
 * controls the person already knows - a bespoke pause button would be worse in
 * every one of those ways. What is added is a close button, because a modal is
 * the app's idea rather than the video element's.
 *
 * Three ways out, because a video covering the page with no obvious exit is a
 * trap: the button, Escape, and clicking the backdrop.
 */
const props = defineProps<{
  open: boolean
  src: string
  mimeType?: string | null
  title: string
}>()

const emit = defineEmits<{ close: [] }>()

const { t } = useI18n()

const video = ref<HTMLVideoElement | null>(null)
const closeButton = ref<HTMLButtonElement | null>(null)
/** What had focus before the dialog opened, so it can be given back. */
const previouslyFocused = ref<HTMLElement | null>(null)

function close(): void {
  emit('close')
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    event.preventDefault()
    close()
  }
}

watch(
  () => props.open,
  async (isOpen) => {
    if (isOpen) {
      previouslyFocused.value = document.activeElement as HTMLElement | null
      document.addEventListener('keydown', onKeydown)

      await nextTick()
      closeButton.value?.focus()

      // Autoplay muted would be more seamless and is the wrong default here:
      // somebody pressed a play button, so they asked for sound, and a silent
      // demonstration of a breathing pattern is not much of a demonstration.
      void video.value?.play().catch(() => {
        // Blocked by the browser's autoplay policy. The controls are right
        // there; this is not worth reporting.
      })

      return
    }

    document.removeEventListener('keydown', onKeydown)

    // Stop the audio the instant it closes. A paused-but-not-reset video would
    // resume mid-sentence the next time the same clip is opened.
    if (video.value) {
      video.value.pause()
      video.value.currentTime = 0
    }

    previouslyFocused.value?.focus()
  },
)

onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <!-- Rendered at the end of <body> so no card's overflow or stacking context
       can clip it. -->
  <Teleport to="body">
    <div
      v-if="open"
      class="backdrop"
      role="dialog"
      aria-modal="true"
      :aria-label="title"
      @click.self="close"
    >
      <div class="player">
        <div class="bar">
          <strong class="title">{{ title }}</strong>
          <button
            ref="closeButton"
            type="button"
            class="ghost close"
            :aria-label="t('common.close')"
            @click="close"
          >
            ✕
          </button>
        </div>

        <video ref="video" controls playsinline preload="metadata" class="video">
          <source :src="src" :type="mimeType ?? undefined" />
          {{ t('activity.videoUnsupported') }}
        </video>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.backdrop {
  position: fixed;
  inset: 0;
  z-index: 100;
  display: grid;
  place-items: center;
  padding: var(--spacing-medium);
  background: rgb(0 0 0 / 60%);
}

.player {
  width: min(560px, 100%);
  background: var(--surface-primary);
  border-radius: var(--border-radius-medium);
  overflow: hidden;
  box-shadow: 0 20px 60px rgb(0 0 0 / 35%);
}

.bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-small);
  padding: var(--spacing-2xs) var(--spacing-small);
  border-bottom: var(--border-width-small) solid var(--border-primary);
}

.title {
  color: var(--text-headings);
  font-size: var(--fontsize-body-medium);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.close { flex: none; font-size: 15px; line-height: 1; }

.video {
  display: block;
  width: 100%;
  /* Capped so a portrait clip filmed on a phone cannot grow taller than the
     window and push its own controls off the bottom. */
  max-height: min(60vh, 420px);
  background: #000;
}
</style>
