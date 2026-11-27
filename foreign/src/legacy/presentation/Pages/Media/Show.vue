<script setup lang="ts">
import { RoutePath } from '../../../../app/router/paths'

import { appPath } from '@/path'
import { Head, Link, usePage, useForm, router } from '@/application/navigation'
import { computed, ref, watch, onUnmounted } from 'vue'
import {
  ChevronDown,
  ChevronUp,
  Columns2,
  LoaderCircle,
  RectangleHorizontal,
} from 'lucide-vue-next'
import AppLayout from '@/Layouts/AppLayout.vue'
import YouTubeSyncedPlayer from '@/Components/media/YouTubeSyncedPlayer.vue'
import TimedTranscriptList from '@/Components/media/TimedTranscriptList.vue'
import {
  findActiveSegmentIndex,
  parseTranscriptSegments,
  type TranscriptSegment,
} from '@/domain/transcript-sync'

type MediaShowItem = {
  id: number
  title: string
  type: string
  source_id: string | null
  url: string | null
  frequency: string | null
  difficulty: string | null
  difficulty_label: string | null
  transcript: string | null
  transcript_segments: TranscriptSegment[] | null
  analysis_status: string | null
  transcript_status?: string | null
  transcript_error?: string | null
}

const props = defineProps<{
  item: MediaShowItem
}>()

const transcriptPending = computed(() =>
  ['queued', 'running'].includes(props.item.transcript_status || ''),
)
let pollTimer: ReturnType<typeof setInterval> | undefined
let polling = false
watch(
  transcriptPending,
  (pending) => {
    clearInterval(pollTimer)
    if (pending)
      pollTimer = setInterval(async () => {
        if (polling) return
        polling = true
        try {
          await router.reload({ background: true })
        } catch {
          /* Retry transient polling failures on the next tick. */
        } finally {
          polling = false
        }
      }, 2000)
  },
  { immediate: true },
)
onUnmounted(() => clearInterval(pollTimer))
const listened = useForm({})
const captionForm = useForm({})
function markListened() {
  listened.post(appPath(`/home/media/${props.item.id}/listened`))
}
function fetchCaptions() {
  captionForm.post(appPath(`/home/media/${props.item.id}/captions`))
}
const page = usePage()
const isFlcApp = computed(() =>
  Boolean((page.props as { isFlcApp?: boolean }).isFlcApp),
)

const youtubeWatchUrl = computed((): string | null => {
  if (props.item.url) {
    return props.item.url
  }
  if (props.item.source_id) {
    return `https://www.youtube.com/watch?v=${props.item.source_id}`
  }
  return null
})

const youtubeThumbUrl = computed((): string | null => {
  if (!props.item.source_id) {
    return null
  }
  return `https://i.ytimg.com/vi/${props.item.source_id}/hqdefault.jpg`
})

const currentTime = ref(0)
const playerRef = ref<InstanceType<typeof YouTubeSyncedPlayer> | null>(null)

const segments = computed((): TranscriptSegment[] =>
  parseTranscriptSegments(props.item.transcript_segments),
)

const hasTimedTranscript = computed((): boolean => segments.value.length > 0)

const activeSegmentIndex = computed((): number =>
  findActiveSegmentIndex(segments.value, currentTime.value),
)

const difficultyLabel = computed((): string => {
  if (props.item.difficulty_label) {
    return props.item.difficulty_label
  }
  if (props.item.difficulty === 'beginner') {
    return 'Beginner'
  }
  if (props.item.difficulty === 'advanced') {
    return 'Advanced'
  }
  return 'Intermediate'
})

const canEmbedYoutube = computed(
  (): boolean =>
    props.item.type === 'youtube' &&
    Boolean(props.item.source_id) &&
    !isFlcApp.value,
)

const VIDEO_WIDE_KEY = 'foreign.media.videoWide'
const TRANSCRIPT_COLLAPSED_KEY = 'foreign.media.transcriptCollapsed'

function readFlag(key: string): boolean {
  try {
    return window.localStorage.getItem(key) === '1'
  } catch {
    return false
  }
}

function writeFlag(key: string, value: boolean): void {
  try {
    window.localStorage.setItem(key, value ? '1' : '0')
  } catch {
    /* Storage may be unavailable (private mode); keep in-memory state. */
  }
}

const videoWide = ref(readFlag(VIDEO_WIDE_KEY))
const transcriptCollapsed = ref(readFlag(TRANSCRIPT_COLLAPSED_KEY))
watch(videoWide, (value) => writeFlag(VIDEO_WIDE_KEY, value))
watch(transcriptCollapsed, (value) =>
  writeFlag(TRANSCRIPT_COLLAPSED_KEY, value),
)

function toggleVideoWide(): void {
  videoWide.value = !videoWide.value
}

function toggleTranscript(): void {
  transcriptCollapsed.value = !transcriptCollapsed.value
}

function onPlayerTimeUpdate(seconds: number): void {
  currentTime.value = seconds
}

function onTranscriptSeek(seconds: number): void {
  playerRef.value?.seekTo(seconds, true)
}
</script>

<template>
  <Head :title="item.title" />
  <AppLayout :title="item.title">
    <template #header>
      <header class="user-header media-show-header">
        <Link
          :href="appPath(RoutePath.media)"
          class="user-header-back user-header-back--round"
          aria-label="Back"
          >←</Link
        >
        <div class="media-show-header-main">
          <h1>{{ item.title }}</h1>
        </div>
        <span class="user-header-spacer" aria-hidden="true" />
      </header>
    </template>

    <div class="media-show-page">
      <div
        class="media-show-main"
        :class="{
          'is-video-wide': videoWide,
          'is-transcript-collapsed': transcriptCollapsed,
        }"
      >
        <a
          v-if="
            item.type === 'youtube' &&
            item.source_id &&
            isFlcApp &&
            youtubeWatchUrl
          "
          :href="youtubeWatchUrl"
          class="video-embed video-embed--external"
          :aria-label="`Open ${item.title} in YouTube`"
        >
          <img
            v-if="youtubeThumbUrl"
            :src="youtubeThumbUrl"
            :alt="item.title"
            class="video-embed-thumb"
          />
          <span class="video-embed-play" aria-hidden="true">▶</span>
          <span class="video-embed-label">Open in YouTube</span>
        </a>
        <YouTubeSyncedPlayer
          v-else-if="canEmbedYoutube && item.source_id"
          ref="playerRef"
          :video-id="item.source_id"
          :title="item.title"
          @timeupdate="onPlayerTimeUpdate"
        >
          <template #toolbar-actions>
            <button
              type="button"
              class="media-layout-toggle"
              :aria-pressed="videoWide"
              :aria-label="
                videoWide ? 'Show transcript beside video' : 'Full width video'
              "
              :title="
                videoWide ? 'Show transcript beside video' : 'Full width video'
              "
              @click="toggleVideoWide"
            >
              <Columns2 v-if="videoWide" :size="20" aria-hidden="true" />
              <RectangleHorizontal v-else :size="20" aria-hidden="true" />
            </button>
          </template>
        </YouTubeSyncedPlayer>
        <p v-else-if="item.url" class="media-show-open-link">
          <a :href="item.url" target="_blank" rel="noopener" class="btn"
            >Open media</a
          >
        </p>

        <section class="transcript-panel" aria-label="Transcript">
          <div class="transcript-panel-head">
            <div class="transcript-panel-heading">
              <h2 class="transcript-panel-title">Transcript</h2>
              <span
                class="difficulty-tag"
                :class="`difficulty-tag--${item.difficulty || 'intermediate'}`"
                >{{ difficultyLabel }}</span
              >
            </div>
            <div class="transcript-panel-actions">
              <button
                v-if="!item.transcript && !hasTimedTranscript"
                type="button"
                class="btn btn-sm btn-secondary"
                :disabled="captionForm.processing || transcriptPending"
                @click="fetchCaptions"
              >
                Fetch captions
              </button>
              <Link
                :href="
                  appPath(
                    RoutePath.transcriptEdit.replace(':id', String(item.id)),
                  )
                "
                class="btn btn-sm btn-secondary"
              >
                {{ item.transcript || hasTimedTranscript ? 'Edit' : 'Add' }}
              </Link>
              <button
                type="button"
                class="transcript-collapse-toggle"
                :aria-expanded="!transcriptCollapsed"
                aria-controls="media-transcript-body"
                :aria-label="
                  transcriptCollapsed ? 'Show transcript' : 'Hide transcript'
                "
                :title="
                  transcriptCollapsed ? 'Show transcript' : 'Hide transcript'
                "
                @click="toggleTranscript"
              >
                <ChevronDown
                  v-if="transcriptCollapsed"
                  :size="18"
                  aria-hidden="true"
                />
                <ChevronUp v-else :size="18" aria-hidden="true" />
              </button>
            </div>
          </div>

          <div
            v-show="!transcriptCollapsed"
            id="media-transcript-body"
            class="transcript-scroll-panel"
          >
            <div class="transcript-view">
              <div
                v-if="transcriptPending && !hasTimedTranscript"
                class="transcript-queue-loading"
                role="status"
                aria-label="Fetching transcript"
              >
                <LoaderCircle
                  class="transcript-queue-spinner"
                  :size="32"
                  aria-hidden="true"
                />
              </div>
              <p
                v-else-if="
                  item.transcript_status === 'failed' && !hasTimedTranscript
                "
                class="alert alert-error"
                role="alert"
              >
                {{
                  item.transcript_error ||
                  'Could not fetch captions. Retry or add a transcript manually.'
                }}
              </p>
              <TimedTranscriptList
                v-else-if="hasTimedTranscript"
                :segments="segments"
                :active-index="activeSegmentIndex"
                @seek="onTranscriptSeek"
              />
              <div v-else-if="item.transcript" class="transcript-text">
                {{ item.transcript }}
              </div>
              <p v-else class="muted transcript-empty">No transcript yet.</p>
            </div>
          </div>
          <button
            type="button"
            class="btn btn-sm btn-secondary"
            :disabled="listened.processing"
            @click="markListened"
          >
            Mark listened
          </button>
        </section>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.media-layout-toggle,
.transcript-collapse-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: var(--primary);
  cursor: pointer;
}
.media-layout-toggle {
  width: 40px;
  height: 40px;
}
.transcript-collapse-toggle {
  width: 32px;
  height: 32px;
}
.media-layout-toggle:hover,
.transcript-collapse-toggle:hover {
  background: var(--primary-soft);
}
.media-layout-toggle:focus-visible,
.transcript-collapse-toggle:focus-visible {
  outline: 2px solid var(--primary);
  outline-offset: 2px;
}
/* Side-by-side layout only exists on wide screens. */
@media (max-width: 1023px) {
  .media-layout-toggle {
    display: none;
  }
}
.transcript-queue-loading {
  display: grid;
  place-items: center;
  min-height: 180px;
  color: var(--primary);
}
.transcript-queue-spinner {
  animation: transcript-queue-spin 0.8s linear infinite;
}
@keyframes transcript-queue-spin {
  to {
    transform: rotate(360deg);
  }
}
@media (prefers-reduced-motion: reduce) {
  .transcript-queue-spinner {
    animation: none;
  }
}
</style>
