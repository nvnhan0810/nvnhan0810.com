<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { decodeQr, decodeQrFile } from '@/infrastructure/qr-decoder'
import { hasCamera } from '@/infrastructure/device'

const props = defineProps<{
  open: boolean
  title?: string
  /** Return `true` to accept a code, or an error message to keep scanning. */
  accept: (raw: string) => true | string
}>()
const emit = defineEmits<{ close: [] }>()

const video = ref<HTMLVideoElement | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const status = ref<'idle' | 'starting' | 'scanning' | 'no-camera'>('idle')
const message = ref('')
const error = ref('')
let stream: MediaStream | null = null
let timer: ReturnType<typeof setTimeout> | undefined
let lastRejected = ''
let active = false

function stop() {
  active = false
  clearTimeout(timer)
  stream?.getTracks().forEach((t) => t.stop())
  stream = null
  if (video.value) video.value.srcObject = null
}

function handle(raw: string | null): boolean {
  if (!raw || raw === lastRejected) return false
  const verdict = props.accept(raw)
  if (verdict === true) {
    stop()
    return true
  }
  lastRejected = raw
  error.value = verdict
  return false
}

async function tick() {
  if (!active || !video.value) return
  try {
    if (video.value.readyState >= 2 && handle(await decodeQr(video.value)))
      return
  } catch {
    // A transient decode failure; keep scanning.
  }
  if (active) timer = setTimeout(tick, 200)
}

async function start() {
  error.value = ''
  lastRejected = ''
  if (!hasCamera()) {
    status.value = 'no-camera'
    message.value =
      'Trình duyệt không mở được camera (cần HTTPS). Hãy chọn ảnh mã QR.'
    return
  }
  status.value = 'starting'
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: 'environment' } },
      audio: false,
    })
    if (!props.open) return stop()
    await nextTick()
    if (!video.value) return stop()
    video.value.srcObject = stream
    await video.value.play()
    status.value = 'scanning'
    active = true
    tick()
  } catch (e) {
    stop()
    status.value = 'no-camera'
    message.value =
      e instanceof DOMException && e.name === 'NotAllowedError'
        ? 'Chưa cấp quyền camera. Cho phép trong cài đặt trình duyệt hoặc chọn ảnh mã QR.'
        : 'Không mở được camera. Hãy chọn ảnh mã QR.'
  }
}

async function pickFile(event: Event) {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  error.value = ''
  lastRejected = ''
  try {
    const raw = await decodeQrFile(file)
    if (!raw) error.value = 'Không tìm thấy mã QR trong ảnh.'
    else handle(raw)
  } catch {
    error.value = 'Không đọc được ảnh.'
  }
}

function close() {
  stop()
  emit('close')
}

watch(
  () => props.open,
  (open) => (open ? start() : stop()),
  { immediate: true },
)
onBeforeUnmount(stop)
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[60] flex flex-col bg-black text-white"
      role="dialog"
      aria-modal="true"
      :aria-label="title || 'Quét mã QR'"
      @keydown.esc="close"
    >
      <header
        class="flex items-center justify-between gap-3 px-4 pt-[calc(0.75rem+env(safe-area-inset-top))] pb-3"
      >
        <h2 class="text-base font-semibold">{{ title || 'Quét mã QR' }}</h2>
        <button
          type="button"
          class="w-10 h-10 -mr-2 rounded-full flex items-center justify-center hover:bg-white/10"
          aria-label="Đóng"
          @click="close"
        >
          <svg
            class="w-6 h-6"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path d="M6 6l12 12M18 6 6 18" />
          </svg>
        </button>
      </header>

      <div class="relative flex-1 overflow-hidden">
        <video
          v-show="status === 'scanning' || status === 'starting'"
          ref="video"
          class="absolute inset-0 w-full h-full object-cover"
          playsinline
          muted
          autoplay
        />
        <div
          v-if="status === 'scanning' || status === 'starting'"
          class="absolute inset-0 flex items-center justify-center pointer-events-none"
        >
          <div
            class="w-64 h-64 max-w-[75vw] max-h-[75vw] rounded-3xl border-4 border-white/90 shadow-[0_0_0_100vmax_rgba(0,0,0,0.45)]"
          />
        </div>
        <p
          v-if="status === 'starting'"
          class="absolute inset-x-0 top-1/2 text-center text-sm text-white/80"
        >
          Đang mở camera…
        </p>
        <div
          v-if="status === 'no-camera'"
          class="absolute inset-0 flex items-center justify-center p-8 text-center text-sm text-white/80"
        >
          {{ message }}
        </div>
      </div>

      <footer
        class="px-4 pt-4 pb-[calc(1rem+env(safe-area-inset-bottom))] space-y-3 text-center"
      >
        <p
          v-if="error"
          class="rounded-lg bg-red-500/20 px-3 py-2 text-sm text-red-100"
          role="alert"
        >
          {{ error }}
        </p>
        <p v-else class="text-sm text-white/70">
          Đưa mã VietQR chuyển khoản vào khung.
        </p>
        <input
          ref="fileInput"
          type="file"
          accept="image/*"
          class="sr-only"
          tabindex="-1"
          @change="pickFile"
        />
        <button
          type="button"
          class="btn w-full bg-white/10 text-white hover:bg-white/20"
          @click="fileInput?.click()"
        >
          Chọn ảnh mã QR
        </button>
      </footer>
    </div>
  </Teleport>
</template>
