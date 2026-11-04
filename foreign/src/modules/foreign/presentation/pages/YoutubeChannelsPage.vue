<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppLayout from '../../../../legacy/presentation/Layouts/AppLayout.vue'
import { Head } from '../../../../legacy/application/navigation'
import { RoutePath } from '../../../../app/router/paths'
import {
  youtubeApi,
  type YoutubeChannel,
  type YoutubeVideo,
  type YoutubeVideoPage,
} from '../../infrastructure/youtube-api'
import { ApiError } from '../../infrastructure/transport'
import { durationLabel } from '../duration'

const channels = ref<YoutubeChannel[]>([])
const selected = ref('')
const channelUrl = ref('')
const loading = ref(true)
const changingChannels = ref(false)
const error = ref('')
const notice = ref('')
const difficulty = ref('intermediate')
const addDialog = ref<HTMLDialogElement | null>(null)
const pendingVideo = ref<YoutubeVideo | null>(null)
const addError = ref('')
const addingVideo = computed(
  () => !!pendingVideo.value && saving.value.has(pendingVideo.value.video_id),
)
const page = ref<YoutubeVideoPage>({ items: [], next_cursor: null })
const cursors = ref<(string | null)[]>([null])
const pageIndex = ref(0)
const saving = ref(new Set<string>())
let request: AbortController | undefined
let generation = 0
const currentChannel = computed(() =>
  channels.value.find((c) => c.channel_id === selected.value),
)
const busy = computed(() => loading.value || changingChannels.value)

function message(cause: unknown): string {
  const code = cause instanceof ApiError ? cause.code : ''
  const messages: Record<string, string> = {
    youtube_not_configured:
      'YouTube is not available yet. Please try again later.',
    youtube_quota_exceeded:
      'YouTube’s daily limit has been reached. Please try again later.',
    youtube_unavailable:
      'YouTube returned an unexpected error. Please try again.',
    youtube_connection_failed:
      'Could not connect to YouTube. Please try again later.',
    youtube_timeout: 'YouTube took too long to respond. Please try again.',
    youtube_invalid_key:
      'YouTube access is not configured correctly. Please contact the administrator.',
    youtube_api_disabled:
      'YouTube API access is not enabled. Please contact the administrator.',
    youtube_key_restricted:
      'YouTube access is blocked by the API key restrictions. Please contact the administrator.',
    youtube_access_denied:
      'YouTube denied access. Please contact the administrator.',
    server_error:
      'The server could not complete the request. Please try again.',

    youtube_invalid_cursor:
      'This video page has expired or your channels changed. Refresh to start again.',
    youtube_not_found: 'This channel or video is no longer available.',
    not_found: 'Channel or video not found. Check the URL and try again.',
    network_error: 'Could not connect. Check your connection and retry.',
  }
  return (
    messages[code] ??
    (code ||
      (cause instanceof Error
        ? cause.message
        : 'Something went wrong. Please retry.'))
  )
}
async function loadVideos(index = 0, reset = false) {
  request?.abort()
  request = new AbortController()
  const version = ++generation
  loading.value = true
  error.value = ''
  if (reset) {
    cursors.value = [null]
    pageIndex.value = 0
    page.value = { items: [], next_cursor: null }
  }
  try {
    const result = await youtubeApi.videos(
      selected.value,
      cursors.value[index] ?? null,
      request.signal,
    )
    if (version !== generation) return
    page.value = result
    pageIndex.value = index
    if (result.next_cursor) cursors.value[index + 1] = result.next_cursor
    cursors.value.length = index + (result.next_cursor ? 2 : 1)
  } catch (cause) {
    if (version === generation) error.value = message(cause)
  } finally {
    if (version === generation) loading.value = false
  }
}
async function refreshChannels() {
  const result = await youtubeApi.channels()
  channels.value = result.items
  if (!channels.value.some((c) => c.channel_id === selected.value))
    selected.value = ''
}
async function initialize() {
  loading.value = true
  error.value = ''
  try {
    await refreshChannels()
    await loadVideos(0, true)
  } catch (cause) {
    error.value = message(cause)
    loading.value = false
  }
}
async function saveChannel() {
  if (!channelUrl.value.trim() || busy.value) return
  changingChannels.value = true
  error.value = ''
  notice.value = ''
  try {
    await youtubeApi.saveChannel(channelUrl.value.trim())
    channelUrl.value = ''
    await refreshChannels()
    notice.value = 'Channel saved.'
    await loadVideos(0, true)
  } catch (cause) {
    error.value = message(cause)
  } finally {
    changingChannels.value = false
  }
}
async function removeChannel(channel: YoutubeChannel) {
  if (
    busy.value ||
    !window.confirm(
      `Remove “${channel.title}” from your channels? Saved listening videos will stay in your library.`,
    )
  )
    return
  changingChannels.value = true
  error.value = ''
  notice.value = ''
  try {
    await youtubeApi.removeChannel(channel.channel_id)
    await refreshChannels()
    await loadVideos(0, true)
  } catch (cause) {
    error.value = message(cause)
  } finally {
    changingChannels.value = false
  }
}
function chooseDifficulty(video: YoutubeVideo) {
  if (video.media_id || saving.value.has(video.video_id)) return
  pendingVideo.value = video
  difficulty.value = 'intermediate'
  addError.value = ''
  addDialog.value?.showModal()
}
function closeAddDialog() {
  if (addingVideo.value) return
  addDialog.value?.close()
  pendingVideo.value = null
}
async function addVideo() {
  const video = pendingVideo.value
  if (!video || video.media_id || saving.value.has(video.video_id)) return
  saving.value.add(video.video_id)
  error.value = ''
  notice.value = ''
  try {
    const result = await youtubeApi.addListening(
      video.video_id,
      difficulty.value,
    )
    video.media_id = result.media_id
    // A refresh may have replaced the card while the save was in flight.
    for (const item of page.value.items)
      if (item.video_id === video.video_id) item.media_id = result.media_id
    notice.value = result.captions_queued
      ? 'Video added to listening.'
      : 'Video saved. Open it and use Fetch captions to retry subtitles.'
    addDialog.value?.close()
    pendingVideo.value = null
  } catch (cause) {
    addError.value = message(cause)
  } finally {
    saving.value.delete(video.video_id)
  }
}
function dateLabel(value: string | null) {
  if (!value) return ''
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString()
}
onMounted(() => void initialize())
onBeforeUnmount(() => {
  generation++
  request?.abort()
})
</script>

<template>
  <Head title="YouTube channels" />
  <AppLayout
    title="YouTube channels"
    heading="YouTube channels"
    :back-href="RoutePath.media"
    disable-pull-to-refresh
  >
    <div class="channels-page">
      <div class="channels-intro">
        <div>
          <p class="channels-eyebrow">YOUR LISTENING SOURCES</p>
          <h2>Learn from your favorite channels</h2>
          <p class="muted">
            Save a channel, browse its videos, and pick your next listening
            practice. Shorts are excluded.
          </p>
        </div>
        <button
          type="button"
          class="btn btn-secondary"
          :disabled="busy"
          @click="initialize"
        >
          Refresh
        </button>
      </div>
      <form class="channel-add" @submit.prevent="saveChannel">
        <label for="channel-url">YouTube channel</label>
        <div class="channel-add-row">
          <input
            id="channel-url"
            v-model="channelUrl"
            class="form-control"
            placeholder="https://www.youtube.com/@channel"
            required
            maxlength="500"
            :disabled="busy"
          /><button
            class="btn"
            type="submit"
            :disabled="busy || !channelUrl.trim()"
          >
            {{ changingChannels ? 'Saving…' : 'Save channel' }}
          </button>
        </div>
        <p class="muted">
          Paste a channel URL, @handle or channel ID. Up to 20 channels.
        </p>
      </form>
      <p v-if="error" class="alert alert-error" role="alert">
        {{ error }}
        <button type="button" :disabled="busy" @click="initialize">
          Retry
        </button>
      </p>
      <p v-if="notice" class="alert alert-success" role="status">
        {{ notice }}
      </p>
      <section
        v-if="channels.length"
        aria-label="Saved channels"
        class="saved-channels"
      >
        <div
          v-for="channel in channels"
          :key="channel.channel_id"
          class="channel-chip"
        >
          <img
            v-if="channel.thumbnail_url"
            :src="channel.thumbnail_url"
            alt=""
            width="28"
            height="28"
            loading="lazy"
            referrerpolicy="no-referrer"
          />
          <span>{{ channel.title }}</span
          ><button
            type="button"
            :aria-label="`Remove ${channel.title}`"
            :disabled="busy"
            @click="removeChannel(channel)"
          >
            ×
          </button>
        </div>
      </section>
      <div v-if="channels.length" class="channel-controls">
        <div class="form-group">
          <label for="channel-filter">Channel</label
          ><select
            id="channel-filter"
            v-model="selected"
            class="form-control"
            :disabled="changingChannels"
            @change="loadVideos(0, true)"
          >
            <option value="">All saved channels</option>
            <option
              v-for="channel in channels"
              :key="channel.channel_id"
              :value="channel.channel_id"
            >
              {{ channel.title }}
            </option>
          </select>
        </div>
      </div>
      <section :aria-busy="loading" aria-label="Channel videos">
        <p v-if="loading" class="empty-state" role="status">Loading videos…</p>
        <div v-else-if="!channels.length && !error" class="empty-state">
          No channels saved yet. Add your first channel above to discover
          videos.
        </div>
        <div v-else-if="!page.items.length && !error" class="empty-state">
          No public videos on this page. Try another channel or continue to the
          next page.
        </div>
        <template v-if="!loading && channels.length">
          <div class="channel-results-heading">
            <h3>{{ currentChannel?.title ?? 'All saved channels' }}</h3>
            <span class="muted">Page {{ pageIndex + 1 }}</span>
          </div>
          <div class="channel-video-grid">
            <article
              v-for="video in page.items"
              :key="video.video_id"
              class="channel-video-card"
              :data-video-id="video.video_id"
            >
              <a
                :href="`https://www.youtube.com/watch?v=${video.video_id}`"
                target="_blank"
                rel="noopener noreferrer"
                class="channel-video-thumb"
                :aria-label="`Watch ${video.title} on YouTube`"
                ><img
                  :src="video.thumbnail_url"
                  :alt="video.title"
                  loading="lazy"
                /><span v-if="video.media_id" class="channel-saved-badge"
                  >✓ Saved</span
                ><span
                  v-if="durationLabel(video.duration)"
                  class="channel-video-duration"
                  :aria-label="`Duration ${durationLabel(video.duration)}`"
                  >{{ durationLabel(video.duration) }}</span
                ></a
              >
              <div class="channel-video-body">
                <p class="muted channel-video-meta">
                  {{ video.channel_title
                  }}<template v-if="dateLabel(video.published_at)">
                    · {{ dateLabel(video.published_at) }}</template
                  >
                </p>
                <h4>{{ video.title }}</h4>
                <RouterLink
                  v-if="video.media_id"
                  :to="
                    RoutePath.mediaDetail.replace(':id', String(video.media_id))
                  "
                  class="btn btn-secondary"
                  >Open listening →</RouterLink
                >
                <button
                  v-else
                  class="btn"
                  type="button"
                  :disabled="saving.has(video.video_id)"
                  @click="chooseDifficulty(video)"
                >
                  {{
                    saving.has(video.video_id)
                      ? 'Adding…'
                      : '+ Add to listening'
                  }}
                </button>
              </div>
            </article>
          </div>
        </template>
        <nav
          v-if="channels.length"
          class="channel-pagination"
          aria-label="Video pages"
        >
          <button
            type="button"
            class="btn btn-secondary"
            :disabled="busy || pageIndex === 0"
            @click="loadVideos(pageIndex - 1)"
          >
            ← Previous</button
          ><span>Page {{ pageIndex + 1 }}</span
          ><button
            type="button"
            class="btn btn-secondary"
            :disabled="busy || !page.next_cursor"
            @click="loadVideos(pageIndex + 1)"
          >
            Next →
          </button>
        </nav>
      </section>
    </div>
    <dialog
      ref="addDialog"
      class="add-listening-dialog"
      aria-labelledby="add-listening-title"
      aria-describedby="add-listening-video"
      @cancel.prevent="closeAddDialog"
    >
      <form v-if="pendingVideo" @submit.prevent="addVideo">
        <h2 id="add-listening-title">Add to listening</h2>
        <p id="add-listening-video" class="muted">{{ pendingVideo.title }}</p>
        <div class="form-group">
          <label for="video-difficulty">Difficulty</label>
          <select
            id="video-difficulty"
            v-model="difficulty"
            class="form-control"
            :disabled="addingVideo"
          >
            <option value="beginner">Beginner</option>
            <option value="intermediate">Intermediate</option>
            <option value="advanced">Advanced</option>
          </select>
        </div>
        <p v-if="addError" class="alert alert-error" role="alert">
          {{ addError }}
        </p>
        <div class="add-listening-actions">
          <button
            type="button"
            class="btn btn-secondary"
            :disabled="addingVideo"
            @click="closeAddDialog"
          >
            Cancel
          </button>
          <button type="submit" class="btn" :disabled="addingVideo">
            {{ addingVideo ? 'Adding…' : 'Add to listening' }}
          </button>
        </div>
      </form>
    </dialog>
  </AppLayout>
</template>

<style scoped>
.add-listening-dialog {
  width: min(420px, calc(100% - 32px));
  max-height: calc(100dvh - 48px);
  overflow-y: auto;
  margin: auto;
  padding: 24px;
  border: 1px solid var(--border);
  border-radius: 20px;
  background: var(--card);
  color: var(--text);
}
.add-listening-dialog::backdrop {
  background: rgb(0 0 0 / 50%);
}
.add-listening-dialog h2 {
  font-size: 1.2rem;
  margin: 0 0 12px;
}
.add-listening-dialog p {
  margin-bottom: 20px;
  overflow-wrap: anywhere;
}
.add-listening-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 20px;
}

.channels-page {
  max-width: 1100px;
  margin: 0 auto;
  padding: 20px 16px 36px;
}
.channels-intro {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 24px;
}
.channels-intro h2 {
  font-size: 1.4rem;
  margin: 6px 0 10px;
}
.channels-eyebrow {
  color: var(--primary);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.12em;
}
.channel-add {
  padding: 18px;
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--card);
  margin-bottom: 20px;
}
.channel-add label {
  display: block;
  font-weight: 600;
  margin-bottom: 8px;
}
.channel-add-row {
  display: flex;
  gap: 10px;
}
.channel-add-row input {
  min-width: 0;
  flex: 1;
}
.channel-add-row button {
  flex-shrink: 0;
}
.channel-add p {
  margin: 10px 0 0;
  font-size: 0.8rem;
}
.saved-channels {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-bottom: 22px;
}
.channel-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 24px;
  padding: 6px 8px;
  font-size: 0.8rem;
  max-width: 100%;
}
.channel-chip img {
  border-radius: 50%;
}
.channel-chip span {
  overflow-wrap: anywhere;
}
.channel-chip button {
  background: transparent;
  border: 0;
  color: var(--muted);
  font-size: 1.3rem;
  padding: 0 8px;
  min-width: 32px;
  min-height: 32px;
  cursor: pointer;
}
.channel-controls {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
}
.channel-results-heading {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: center;
  margin: 14px 0;
}
.channel-results-heading h3 {
  font-size: 1rem;
}
.channel-video-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr));
  gap: 18px;
}
.channel-video-card {
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  background: var(--card);
  display: flex;
  flex-direction: column;
}
.channel-video-thumb {
  display: block;
  position: relative;
  aspect-ratio: 16 / 9;
  overflow: hidden;
  background: var(--bg);
}
.channel-video-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.channel-video-duration {
  position: absolute;
  right: 10px;
  bottom: 10px;
  padding: 3px 6px;
  border-radius: 4px;
  background: rgb(0 0 0 / 80%);
  color: white;
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.3;
  font-variant-numeric: tabular-nums;
}
.channel-saved-badge {
  position: absolute;
  top: 10px;
  right: 10px;
  border-radius: 20px;
  padding: 5px 10px;
  background: #166534;
  color: white;
  font-size: 0.75rem;
  font-weight: 700;
}
.channel-video-body {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  flex: 1;
}
.channel-video-meta {
  font-size: 0.75rem;
  margin: 0;
}
.channel-video-body h4 {
  font-size: 0.95rem;
  line-height: 1.5;
  margin: 0 0 8px;
  overflow-wrap: anywhere;
}
.channel-video-body .btn {
  margin-top: auto;
  text-align: center;
}
.channel-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-top: 24px;
}
@media (max-width: 480px) {
  .channels-intro {
    align-items: flex-start;
  }
  .channel-add-row {
    flex-direction: column;
  }
  .channel-controls {
    grid-template-columns: 1fr;
    gap: 0;
  }
  .channels-intro h2 {
    font-size: 1.15rem;
  }
}
</style>
