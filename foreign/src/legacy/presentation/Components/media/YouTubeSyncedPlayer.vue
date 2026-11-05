<script setup lang="ts">
import { Play, Pause, RotateCcw, RotateCw, ExternalLink } from 'lucide-vue-next'
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
  createYoutubePlayer,
  YoutubePlayerState,
  type YoutubePlayer,
} from '@/infrastructure/youtube-iframe-api'

const TIME_POLL_MS = 250

const props = defineProps<{
  videoId: string
  title: string
}>()

const emit = defineEmits<{
  timeupdate: [seconds: number]
  ready: []
  playingchange: [isPlaying: boolean]
}>()

const hostRef = ref<HTMLElement | null>(null)
const playerRef = ref<YoutubePlayer | null>(null)
const pollTimer = ref<number | null>(null)
const currentTime = ref(0)
const duration = ref(0)
const ready = ref(false)
const isPlaying = ref(false)
const playerError = ref(false)
let generation = 0
let captionTimers: number[] = []

function clearCaptionTimers(): void {
  captionTimers.forEach((timer) => window.clearTimeout(timer))
  captionTimers = []
}

function disableCaptions(player: YoutubePlayer): void {
  clearCaptionTimers()
  const request = generation
  const disable = (): void => {
    if (request !== generation) return
    // iOS can initialize captions after ready or restore them on playback.
    // This optional player method is best-effort; failure must not break playback.
    try {
      player.unloadModule?.('captions')
    } catch {
      // Some embedded players do not expose caption module controls.
    }
  }
  disable()
  captionTimers = [250, 1000, 2500].map((delay) =>
    window.setTimeout(disable, delay),
  )
}

function clearPoll(): void {
  if (pollTimer.value !== null) {
    window.clearInterval(pollTimer.value)
    pollTimer.value = null
  }
}

function emitCurrentTime(player: YoutubePlayer): void {
  if (typeof player.getCurrentTime !== 'function') {
    return
  }

  currentTime.value = player.getCurrentTime()
  const length = player.getDuration?.() ?? 0
  duration.value = Number.isFinite(length) ? length : 0
  emit('timeupdate', currentTime.value)
}

function setPlaying(next: boolean): void {
  if (isPlaying.value === next) {
    return
  }
  isPlaying.value = next
  emit('playingchange', next)
}

function startPoll(player: YoutubePlayer): void {
  clearPoll()
  emitCurrentTime(player)
  pollTimer.value = window.setInterval(() => {
    emitCurrentTime(player)
  }, TIME_POLL_MS)
}

function handleStateChange(state: number, player: YoutubePlayer): void {
  if (state === YoutubePlayerState.Playing) {
    disableCaptions(player)
    setPlaying(true)
    startPoll(player)
    return
  }

  setPlaying(false)
  clearPoll()
  emitCurrentTime(player)
}

async function mountPlayer(): Promise<void> {
  const request = generation
  playerError.value = false
  const host = hostRef.value
  if (!host || !props.videoId) {
    return
  }

  host.replaceChildren()
  const mount = document.createElement('div')
  host.appendChild(mount)

  try {
    const player = await createYoutubePlayer({
      element: mount,
      videoId: props.videoId,
      onReady: (readyPlayer): void => {
        if (request !== generation) return
        ready.value = true
        playerRef.value = readyPlayer
        disableCaptions(readyPlayer)
        emit('ready')
        emitCurrentTime(readyPlayer)
      },
      onStateChange: (state, changedPlayer): void => {
        if (request === generation) handleStateChange(state, changedPlayer)
      },
    })

    if (request !== generation) {
      player.destroy()
      return
    }
    playerRef.value = player
  } catch {
    if (request === generation) playerError.value = true
  }
}

function destroyPlayer(): void {
  generation += 1
  clearCaptionTimers()
  ready.value = false
  currentTime.value = 0
  duration.value = 0
  clearPoll()
  setPlaying(false)
  const player = playerRef.value
  playerRef.value = null

  if (player && typeof player.destroy === 'function') {
    player.destroy()
  }

  hostRef.value?.replaceChildren()
}

function seekTo(seconds: number, andPlay = true): void {
  const player = playerRef.value
  if (!player || typeof player.seekTo !== 'function') {
    return
  }

  seconds = Math.max(0, Math.min(seconds, duration.value || Infinity))
  currentTime.value = seconds
  player.seekTo(seconds, true)
  emit('timeupdate', seconds)

  if (andPlay && typeof player.playVideo === 'function') {
    player.playVideo()
  }
}

function play(): void {
  const player = playerRef.value
  if (player && typeof player.playVideo === 'function') {
    setPlaying(true)
    player.playVideo()
  }
}

function pause(): void {
  const player = playerRef.value
  if (player && typeof player.pauseVideo === 'function') {
    setPlaying(false)
    player.pauseVideo()
    emitCurrentTime(player)
  }
}

function togglePlayback(): boolean {
  if (isPlaying.value) {
    pause()
    return false
  }

  play()
  return true
}

function onPlaybackKeydown(event: KeyboardEvent): void {
  if (
    !ready.value ||
    event.defaultPrevented ||
    event.isComposing ||
    event.metaKey ||
    event.ctrlKey ||
    event.altKey ||
    event.shiftKey
  )
    return
  const target = event.target
  if (
    target instanceof HTMLElement &&
    (target.closest('input, textarea, select, [role="textbox"]') ||
      target.isContentEditable)
  )
    return

  const key = event.code || event.key
  if (!['Space', ' ', 'ArrowLeft', 'ArrowRight'].includes(key)) return
  event.preventDefault()
  if ((key === 'Space' || key === ' ') && event.repeat) return
  if (key === 'Space' || key === ' ') togglePlayback()
  else seekTo(currentTime.value + (key === 'ArrowLeft' ? -10 : 10), false)
}

function formatTime(seconds: number): string {
  const value = Math.max(0, Math.floor(seconds))
  const hours = Math.floor(value / 3600)
  const minutes = Math.floor((value % 3600) / 60)
  return `${hours ? `${hours}:` : ''}${String(minutes).padStart(hours ? 2 : 1, '0')}:${String(value % 60).padStart(2, '0')}`
}

function scrub(event: Event): void {
  seekTo(Number((event.target as HTMLInputElement).value), false)
}

watch(
  () => props.videoId,
  async () => {
    destroyPlayer()
    await mountPlayer()
  },
)

onMounted(() => {
  window.addEventListener('keydown', onPlaybackKeydown)
  void mountPlayer()
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onPlaybackKeydown)
  destroyPlayer()
})

defineExpose({
  seekTo,
  play,
  pause,
  togglePlayback,
  isPlaying,
})
</script>

<template>
  <div
    class="video-embed video-embed--youtube-api simple-youtube-player"
    :aria-label="title"
  >
    <div class="simple-youtube-video">
      <div ref="hostRef" class="youtube-player-host" />
      <div class="simple-youtube-interaction-shield" aria-hidden="true" />
    </div>
    <div class="simple-player-controls">
      <input
        type="range"
        min="0"
        :max="duration || 0"
        step="0.1"
        :value="currentTime"
        :disabled="!ready || !duration"
        aria-label="Seek video"
        :aria-valuetext="formatTime(currentTime)"
        @input="scrub"
      />
      <div class="simple-player-toolbar">
        <button
          type="button"
          :disabled="!ready"
          :aria-label="isPlaying ? 'Pause' : 'Play'"
          @click="togglePlayback"
        >
          <Pause v-if="isPlaying" :size="20" /><Play v-else :size="20" />
        </button>
        <button
          type="button"
          :disabled="!ready"
          aria-label="Rewind 10 seconds"
          @click="seekTo(currentTime - 10, false)"
        >
          <RotateCcw :size="20" /><span>10</span>
        </button>
        <button
          type="button"
          :disabled="!ready"
          aria-label="Forward 10 seconds"
          @click="seekTo(currentTime + 10, false)"
        >
          <RotateCw :size="20" /><span>10</span>
        </button>
        <span class="simple-player-time"
          >{{ formatTime(currentTime) }} / {{ formatTime(duration) }}</span
        >
        <a
          :href="`https://www.youtube.com/watch?v=${videoId}`"
          target="_blank"
          rel="noopener noreferrer"
          aria-label="Open in YouTube"
          title="Open in YouTube"
          ><ExternalLink :size="20"
        /></a>
      </div>
    </div>
    <p v-if="playerError" role="alert">
      Player unavailable. Open in YouTube to watch.
    </p>
  </div>
</template>

<style scoped>
.simple-youtube-player {
  height: auto;
  padding-bottom: 0;
  background: var(--card);
  border: 1px solid var(--border);
}
.simple-youtube-video {
  position: relative;
  aspect-ratio: 16 / 9;
  background: #000;
}
.simple-youtube-interaction-shield {
  position: absolute;
  inset: 0;
  z-index: 1;
  background: transparent;
  pointer-events: auto;
}
.simple-player-controls {
  padding: 8px 10px;
}
.simple-player-controls input {
  display: block;
  width: 100%;
  margin: 0;
  accent-color: var(--primary);
  cursor: pointer;
}
.simple-player-toolbar {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-top: 4px;
}
.simple-player-toolbar button,
.simple-player-toolbar a {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  flex-shrink: 0;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: var(--primary);
  cursor: pointer;
}
.simple-player-toolbar button:disabled {
  opacity: 0.4;
  cursor: default;
}
.simple-player-toolbar button span {
  position: absolute;
  font-size: 8px;
  font-weight: 700;
}
.simple-player-toolbar a {
  margin-left: auto;
}
.simple-player-time {
  font-size: 12px;
  color: var(--muted);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}
.simple-player-shortcuts {
  margin: 0;
  font-size: 12px;
  color: var(--muted);
  text-align: center;
}
.simple-youtube-player p {
  padding: 0 12px 12px;
}
</style>
