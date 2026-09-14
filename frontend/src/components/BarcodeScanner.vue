<script setup lang="ts">
import { onBeforeUnmount, ref, useTemplateRef } from 'vue'
import { BrowserMultiFormatReader, type IScannerControls } from '@zxing/browser'
import { BarcodeFormat, DecodeHintType } from '@zxing/library'

/**
 * Reads a product barcode through the device camera.
 *
 * Scanning is an optimisation, never the only route: cameras get denied, laptops
 * have bad ones, and packaging gets scuffed. The manual entry field below the
 * viewfinder is always there, and it is what the component falls back to when
 * anything goes wrong.
 *
 * The camera needs a secure context - https, or localhost, which is why it works
 * in development.
 */
const emit = defineEmits<{ detected: [barcode: string] }>()

const video = useTemplateRef<HTMLVideoElement>('video')

const scanning = ref(false)
const error = ref('')
const manualCode = ref('')

let controls: IScannerControls | null = null

/**
 * Restricted to the formats that actually appear on food packaging. Letting
 * ZXing try every format it knows makes it slower and more prone to misreads.
 */
function createReader(): BrowserMultiFormatReader {
  const hints = new Map()
  hints.set(DecodeHintType.POSSIBLE_FORMATS, [
    BarcodeFormat.EAN_13,
    BarcodeFormat.EAN_8,
    BarcodeFormat.UPC_A,
    BarcodeFormat.UPC_E,
    BarcodeFormat.QR_CODE,
  ])

  return new BrowserMultiFormatReader(hints)
}

async function start(): Promise<void> {
  error.value = ''

  if (!navigator.mediaDevices?.getUserMedia) {
    error.value = 'This browser cannot use the camera. Type the number in instead.'
    return
  }

  scanning.value = true

  try {
    const reader = createReader()

    controls = await reader.decodeFromVideoDevice(
      // Undefined lets the browser choose; on a phone that is the rear camera.
      undefined,
      video.value ?? undefined,
      (result, decodeError) => {
        if (result) {
          emit('detected', result.getText())
          stop()
        }

        // decodeError fires on every frame that does not contain a readable
        // code, which is most of them. Only genuine faults are worth reporting,
        // and those surface as an exception from decodeFromVideoDevice instead.
        void decodeError
      },
    )
  } catch (e) {
    scanning.value = false
    error.value =
      e instanceof DOMException && e.name === 'NotAllowedError'
        ? 'Camera access was denied. Type the number in instead.'
        : 'Could not start the camera. Type the number in instead.'
  }
}

function stop(): void {
  controls?.stop()
  controls = null
  scanning.value = false
}

function submitManual(): void {
  const code = manualCode.value.replace(/\D+/g, '')

  if (code.length < 8) {
    error.value = 'A barcode is at least 8 digits.'
    return
  }

  error.value = ''
  emit('detected', code)
  manualCode.value = ''
}

// Releases the camera when the component goes away - without this the indicator
// light stays on after navigating elsewhere.
onBeforeUnmount(stop)
</script>

<template>
  <div class="scanner stack">
    <div v-if="scanning" class="viewfinder">
      <video ref="video" class="video" muted playsinline></video>
      <div class="reticle" aria-hidden="true"></div>
    </div>

    <div class="row">
      <button v-if="!scanning" class="secondary" type="button" @click="start">
        Scan a barcode
      </button>
      <button v-else class="secondary" type="button" @click="stop">Stop scanning</button>

      <span v-if="scanning" class="muted small">Hold the barcode inside the frame.</span>
    </div>

    <form class="row manual" @submit.prevent="submitManual">
      <input
        v-model="manualCode"
        type="text"
        inputmode="numeric"
        placeholder="…or type the barcode number"
        aria-label="Barcode number"
      />
      <button class="secondary" type="submit">Look up</button>
    </form>

    <p v-if="error" class="alert alert-error small">{{ error }}</p>
  </div>
</template>

<style scoped>
.viewfinder {
  position: relative;
  border-radius: var(--radius-sm);
  overflow: hidden;
  background: #000;
  aspect-ratio: 4 / 3;
  max-height: 260px;
}

.video { width: 100%; height: 100%; object-fit: cover; display: block; }

.reticle {
  position: absolute;
  inset: 22% 12%;
  border: 2px solid rgb(255 255 255 / 85%);
  border-radius: 8px;
  box-shadow: 0 0 0 100vmax rgb(0 0 0 / 35%);
}

.manual input { flex: 1; min-width: 180px; }
</style>
