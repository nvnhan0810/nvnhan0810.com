<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import {
  Plus,
  X,
  Check,
  RefreshCw,
  Search,
  Youtube,
  LayoutGrid,
  Headphones,
  ListPlus,
  ChevronLeft,
  ChevronRight,
  LoaderCircle,
} from 'lucide-vue-next'
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
const channelSearch = ref('')
const filteredChannels = computed(() => {
  const query = channelSearch.value.trim().toLocaleLowerCase()
  return channels.value.filter((channel) =>
    channel.title.toLocaleLowerCase().includes(query),
  )
})
async function chooseChannel(id: string) {
  if (changingChannels.value || selected.value === id) return
  selected.value = id
  await loadVideos(0, true)
}
const channelUrl = ref('')
const channelDialog = ref<HTMLDialogElement | null>(null)
const channelError = ref('')
function openChannelDialog() {
  channelUrl.value = ''
  channelError.value = ''
  channelDialog.value?.showModal()
}
function closeChannelDialog() {
  if (!changingChannels.value) channelDialog.value?.close()
}
const loading = ref(true)
const changingChannels = ref(false)
const error = ref('')
const notice = ref('')
const durationSettingsKey = 'foreign.youtube.duration-filter'
function readDurationSettings(): { enabled: boolean; minutes: number } {
  try {
    const saved = JSON.parse(
      localStorage.getItem(durationSettingsKey) ?? 'null',
    )
    if (
      saved &&
      typeof saved.enabled === 'boolean' &&
      Number.isInteger(saved.minutes) &&
      saved.minutes >= 1 &&
      saved.minutes <= 1440
    )
      return saved
  } catch {
    /* Storage may be unavailable; use the default settings. */
  }
  return { enabled: false, minutes: 15 }
}
const savedDurationSettings = readDurationSettings()
const durationFilterEnabled = ref(savedDurationSettings.enabled)
const maxDurationMinutes = ref<number | string>(savedDurationSettings.minutes)
const activeDurationLimit = ref<number | null>(
  savedDurationSettings.enabled ? savedDurationSettings.minutes * 60 : null,
)
const durationSettingsError = ref('')
const durationDialog = ref<HTMLDialogElement | null>(null)
function openDurationDialog() {
  durationSettingsError.value = ''
  durationDialog.value?.showModal()
}
function closeDurationDialog() {
  durationDialog.value?.close()
}
async function applyDurationSettings() {
  const minutes = Number(maxDurationMinutes.value)
  if (!Number.isInteger(minutes) || minutes < 1 || minutes > 1440) {
    if (durationFilterEnabled.value) {
      durationSettingsError.value =
        'Enter a maximum duration from 1 to 1440 minutes.'
      return
    }
  }
  durationSettingsError.value = ''
  const validMinutes =
    Number.isInteger(minutes) && minutes >= 1 && minutes <= 1440 ? minutes : 15
  maxDurationMinutes.value = validMinutes
  activeDurationLimit.value = durationFilterEnabled.value
    ? validMinutes * 60
    : null
  try {
    localStorage.setItem(
      durationSettingsKey,
      JSON.stringify({
        enabled: durationFilterEnabled.value,
        minutes: validMinutes,
      }),
    )
  } catch {
    /* Filtering still works if browser storage is blocked. */
  }
  await loadVideos(0, true)
}
const difficulty = ref('intermediate')
const addDialog = ref<HTMLDialogElement | null>(null)
const pendingVideo = ref<YoutubeVideo | null>(null)
const previewDialog = ref<HTMLDialogElement | null>(null)
const previewVideo = ref<YoutubeVideo | null>(null)
async function openPreview(video: YoutubeVideo) {
  previewVideo.value = video
  await nextTick()
  if (previewVideo.value) previewDialog.value?.showModal()
}
function closePreview() {
  previewVideo.value = null
  previewDialog.value?.close()
}
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
      activeDurationLimit.value,
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
  channelError.value = ''
  notice.value = ''
  try {
    await youtubeApi.saveChannel(channelUrl.value.trim())
    channelUrl.value = ''
    await refreshChannels()
    channelDialog.value?.close()
    notice.value = 'Channel saved.'
    await loadVideos(0, true)
  } catch (cause) {
    channelError.value = message(cause)
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
  channelError.value = ''
  notice.value = ''
  try {
    await youtubeApi.removeChannel(channel.channel_id)
    await refreshChannels()
    await loadVideos(0, true)
  } catch (cause) {
    channelError.value = message(cause)
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
      <div class="channel-toolbar">
        <div class="channel-search">
          <Search :size="18" aria-hidden="true" />
          <input
            id="channel-search"
            v-model="channelSearch"
            type="search"
            class="form-control"
            placeholder="Search channels"
            aria-label="Search channels"
            :disabled="changingChannels"
          />
        </div>
        <button
          type="button"
          class="channel-icon-button"
          :class="{ 'channel-icon-primary': activeDurationLimit !== null }"
          aria-label="Duration settings"
          title="Duration settings"
          aria-haspopup="dialog"
          :disabled="changingChannels"
          @click="openDurationDialog"
        >
          {{
            activeDurationLimit !== null
              ? activeDurationLimit / 60
              : maxDurationMinutes
          }}
        </button>
        <button
          type="button"
          class="channel-icon-button"
          aria-label="Refresh videos"
          title="Refresh"
          :disabled="busy"
          @click="loadVideos(pageIndex)"
        >
          <RefreshCw
            :size="19"
            :class="{ spinning: loading }"
            aria-hidden="true"
          />
        </button>
        <button
          type="button"
          class="channel-icon-button channel-icon-primary"
          aria-label="Add channel"
          title="Add channel"
          :disabled="busy"
          @click="openChannelDialog"
        >
          <Plus :size="21" aria-hidden="true" />
        </button>
      </div>
      <div class="channel-tags" role="group" aria-label="Filter by channel">
        <button
          type="button"
          class="channel-tag"
          :class="{ active: selected === '' }"
          :aria-pressed="selected === ''"
          data-channel-id=""
          :disabled="changingChannels"
          @click="chooseChannel('')"
        >
          <LayoutGrid :size="16" aria-hidden="true" />All
        </button>
        <button
          v-for="channel in filteredChannels"
          :key="channel.channel_id"
          type="button"
          class="channel-tag"
          :class="{ active: selected === channel.channel_id }"
          :aria-pressed="selected === channel.channel_id"
          :data-channel-id="channel.channel_id"
          :disabled="changingChannels"
          @click="chooseChannel(channel.channel_id)"
        >
          <img
            v-if="channel.thumbnail_url"
            :src="channel.thumbnail_url"
            alt=""
            width="20"
            height="20"
            loading="lazy"
            referrerpolicy="no-referrer"
          />
          <Youtube v-else :size="16" aria-hidden="true" />
          {{ channel.title }}
        </button>
        <span
          v-if="channelSearch.trim() && !filteredChannels.length"
          class="muted channel-search-empty"
          >No channels found.</span
        >
      </div>
      <p v-if="error" class="alert alert-error channel-alert" role="alert">
        {{ error
        }}<button
          type="button"
          class="channel-icon-button"
          aria-label="Retry"
          title="Retry"
          :disabled="busy"
          @click="initialize"
        >
          <RefreshCw :size="18" aria-hidden="true" />
        </button>
      </p>
      <p v-if="notice" class="alert alert-success" role="status">
        {{ notice }}
      </p>
      <section :aria-busy="loading" aria-label="Channel videos">
        <p v-if="loading" class="empty-state" role="status">Loading…</p>
        <p v-else-if="!channels.length && !error" class="empty-state">
          No channels yet.
        </p>
        <p v-else-if="!page.items.length && !error" class="empty-state">
          {{
            activeDurationLimit !== null
              ? 'No videos within your duration limit on this page.'
              : 'No videos on this page.'
          }}
        </p>
        <div v-if="!loading && channels.length" class="channel-video-grid">
          <article
            v-for="video in page.items"
            :key="video.video_id"
            class="channel-video-card"
            :data-video-id="video.video_id"
          >
            <button
              type="button"
              class="channel-video-thumb"
              :aria-label="`Preview ${video.title}`"
              aria-haspopup="dialog"
              @click="openPreview(video)"
            >
              <img
                :src="video.thumbnail_url"
                :alt="video.title"
                loading="lazy"
              />
              <span v-if="video.media_id" class="channel-saved-badge"
                ><Check :size="12" aria-hidden="true" />Saved</span
              >
              <span
                v-if="durationLabel(video.duration)"
                class="channel-video-duration"
                :aria-label="`Duration ${durationLabel(video.duration)}`"
                >{{ durationLabel(video.duration) }}</span
              >
            </button>
            <div class="channel-video-body">
              <p class="muted channel-video-meta">
                {{ video.channel_title
                }}<template v-if="dateLabel(video.published_at)">
                  · {{ dateLabel(video.published_at) }}</template
                >
              </p>
              <h4 :title="video.title">
                <button
                  type="button"
                  class="channel-video-title"
                  aria-haspopup="dialog"
                  @click="openPreview(video)"
                >
                  {{ video.title }}
                </button>
              </h4>
              <div class="channel-video-actions">
                <RouterLink
                  v-if="video.media_id"
                  :to="
                    RoutePath.mediaDetail.replace(':id', String(video.media_id))
                  "
                  class="channel-icon-button channel-open-listening"
                  aria-label="Open listening"
                  title="Open listening"
                  ><Headphones :size="21" aria-hidden="true"
                /></RouterLink>
                <button
                  v-else
                  type="button"
                  class="channel-icon-button channel-icon-primary"
                  aria-label="Add to listening"
                  title="Add to listening"
                  :disabled="saving.has(video.video_id)"
                  @click="chooseDifficulty(video)"
                >
                  <LoaderCircle
                    v-if="saving.has(video.video_id)"
                    :size="21"
                    class="spinning"
                    aria-hidden="true"
                  /><ListPlus v-else :size="21" aria-hidden="true" />
                </button>
              </div>
            </div>
          </article>
        </div>
        <nav
          v-if="channels.length"
          class="channel-pagination"
          aria-label="Video pages"
        >
          <button
            type="button"
            class="channel-icon-button"
            aria-label="Previous page"
            title="Previous page"
            :disabled="busy || pageIndex === 0"
            @click="loadVideos(pageIndex - 1)"
          >
            <ChevronLeft :size="21" aria-hidden="true" />
          </button>
          <span class="muted">Page {{ pageIndex + 1 }}</span>
          <button
            type="button"
            class="channel-icon-button"
            aria-label="Next page"
            title="Next page"
            :disabled="busy || !page.next_cursor"
            @click="loadVideos(pageIndex + 1)"
          >
            <ChevronRight :size="21" aria-hidden="true" />
          </button>
        </nav>
      </section>
    </div>

    <dialog
      ref="previewDialog"
      class="video-preview-dialog"
      aria-labelledby="video-preview-title"
      @cancel.prevent="closePreview"
      @close="previewVideo = null"
    >
      <template v-if="previewVideo">
        <div class="channel-dialog-header">
          <h2 id="video-preview-title">{{ previewVideo.title }}</h2>
          <button
            type="button"
            class="channel-icon-button"
            aria-label="Close video preview"
            title="Close"
            autofocus
            @click="closePreview"
          >
            <X :size="20" aria-hidden="true" />
          </button>
        </div>
        <iframe
          :key="previewVideo.video_id"
          class="video-preview-player"
          :src="`https://www.youtube.com/embed/${encodeURIComponent(previewVideo.video_id)}?playsinline=1`"
          :title="previewVideo.title"
          allow="
            accelerometer;
            autoplay;
            clipboard-write;
            encrypted-media;
            gyroscope;
            picture-in-picture;
            web-share;
          "
          referrerpolicy="strict-origin-when-cross-origin"
          allowfullscreen
        />
        <p class="video-preview-description">
          {{ previewVideo.description || 'No description available.' }}
        </p>
      </template>
    </dialog>

    <dialog
      ref="durationDialog"
      class="duration-settings-dialog"
      aria-labelledby="duration-dialog-title"
      @cancel.prevent="closeDurationDialog"
    >
      <div class="channel-dialog-header">
        <h2 id="duration-dialog-title">Duration filter</h2>
        <button
          type="button"
          class="channel-icon-button"
          aria-label="Close duration settings"
          title="Close"
          @click="closeDurationDialog"
        >
          <X :size="20" aria-hidden="true" />
        </button>
      </div>
      <form
        class="duration-filter-settings"
        aria-label="Video duration settings"
        @submit.prevent="applyDurationSettings"
      >
        <label class="duration-toggle-row" for="duration-filter-enabled"
          ><span>Filter by duration</span
          ><input
            id="duration-filter-enabled"
            v-model="durationFilterEnabled"
            class="duration-switch"
            type="checkbox"
            role="switch"
            aria-label="Filter by duration"
            :disabled="changingChannels"
            @change="applyDurationSettings"
        /></label>
        <label class="duration-limit-label" for="max-duration-minutes"
          >Maximum duration (minutes)</label
        >
        <input
          id="max-duration-minutes"
          v-model.number="maxDurationMinutes"
          type="number"
          min="1"
          max="1440"
          step="1"
          class="form-control"
          aria-label="Maximum duration (minutes)"
          :disabled="!durationFilterEnabled || changingChannels"
          @change="applyDurationSettings"
        />
        <p v-if="durationSettingsError" class="alert alert-error" role="alert">
          {{ durationSettingsError }}
        </p>
      </form>
    </dialog>

    <dialog
      ref="channelDialog"
      class="channel-dialog"
      aria-labelledby="channel-dialog-title"
      @cancel.prevent="closeChannelDialog"
    >
      <div class="channel-dialog-header">
        <h2 id="channel-dialog-title">Channels</h2>
        <button
          type="button"
          class="channel-icon-button"
          aria-label="Close channels"
          title="Close"
          :disabled="changingChannels"
          @click="closeChannelDialog"
        >
          <X :size="20" aria-hidden="true" />
        </button>
      </div>
      <form
        id="channel-add-form"
        class="channel-add"
        @submit.prevent="saveChannel"
      >
        <input
          id="channel-url"
          v-model="channelUrl"
          class="form-control"
          aria-label="YouTube channel URL or handle"
          placeholder="YouTube URL or @handle"
          required
          maxlength="500"
          :disabled="busy"
        />
        <button
          type="submit"
          class="channel-icon-button channel-icon-primary"
          aria-label="Save channel"
          title="Save channel"
          :disabled="busy || !channelUrl.trim()"
        >
          <LoaderCircle
            v-if="changingChannels"
            :size="20"
            class="spinning"
            aria-hidden="true"
          /><Check v-else :size="20" aria-hidden="true" />
        </button>
      </form>
      <p v-if="channelError" class="alert alert-error" role="alert">
        {{ channelError }}
      </p>
      <div
        v-if="channels.length"
        class="saved-channels"
        aria-label="Saved channels"
      >
        <div
          v-for="channel in channels"
          :key="channel.channel_id"
          class="channel-row"
        >
          <img
            v-if="channel.thumbnail_url"
            :src="channel.thumbnail_url"
            alt=""
            width="32"
            height="32"
            loading="lazy"
            referrerpolicy="no-referrer"
          />
          <span>{{ channel.title }}</span>
          <button
            type="button"
            class="channel-icon-button"
            :aria-label="`Remove ${channel.title}`"
            :title="`Remove ${channel.title}`"
            :disabled="busy"
            @click="removeChannel(channel)"
          >
            <X :size="17" aria-hidden="true" />
          </button>
        </div>
      </div>
    </dialog>

    <dialog
      ref="addDialog"
      class="add-listening-dialog"
      aria-labelledby="add-listening-title"
      aria-describedby="add-listening-video"
      @cancel.prevent="closeAddDialog"
    >
      <form v-if="pendingVideo" @submit.prevent="addVideo">
        <div class="channel-dialog-header">
          <h2 id="add-listening-title">Add to listening</h2>
          <button
            type="button"
            class="channel-icon-button"
            aria-label="Cancel adding video"
            title="Cancel"
            :disabled="addingVideo"
            @click="closeAddDialog"
          >
            <X :size="20" aria-hidden="true" />
          </button>
        </div>
        <p id="add-listening-video" class="muted">{{ pendingVideo.title }}</p>
        <div class="form-group">
          <label for="video-difficulty">Difficulty</label
          ><select
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
          <button type="submit" class="btn" :disabled="addingVideo">
            <LoaderCircle
              v-if="addingVideo"
              :size="18"
              class="spinning"
              aria-hidden="true"
            /><Check v-else :size="18" aria-hidden="true" />{{
              addingVideo ? 'Adding…' : 'Add'
            }}
          </button>
        </div>
      </form>
    </dialog>
  </AppLayout>
</template>

<style scoped>
.channels-page {
  width: 100%;
  min-width: 0;
  margin: 0;
  padding: 0 0 24px;
}
.channel-toolbar {
  display: flex;
  flex-wrap: nowrap;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}
.channel-search {
  position: relative;
  flex: 1;
  min-width: 0;
}
.channel-search > svg {
  position: absolute;
  top: 50%;
  left: 12px;
  transform: translateY(-50%);
  color: var(--muted);
  pointer-events: none;
}
.channel-search input {
  width: 100%;
  min-width: 0;
  height: 40px;
  padding-left: 38px;
}
.channel-tags {
  display: flex;
  flex-wrap: nowrap;
  align-items: center;
  gap: 8px;
  overflow-x: auto;
  overscroll-behavior-x: contain;
  scrollbar-width: thin;
  padding: 0 0 8px;
  margin-bottom: 16px;
}
.channel-tag {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  flex: 0 0 auto;
  white-space: nowrap;
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 8px 14px;
  background: var(--card);
  color: var(--text);
  font: inherit;
  font-size: 0.85rem;
  cursor: pointer;
}
.channel-tag img {
  border-radius: 50%;
  object-fit: cover;
  flex-shrink: 0;
}
.channel-tag svg {
  flex-shrink: 0;
}
.channel-tag.active {
  border-color: var(--primary);
  background: var(--primary-soft);
  color: var(--primary);
}
.channel-tag:focus-visible {
  outline: 2px solid var(--primary);
  outline-offset: -3px;
}
.channel-tag:disabled {
  opacity: 0.45;
  cursor: default;
}
.channel-search-empty {
  flex-shrink: 0;
  font-size: 0.85rem;
}
.channel-icon-button {
  font: inherit;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 40px;
  height: 40px;
  padding: 0;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--card);
  color: var(--text);
  cursor: pointer;
  text-decoration: none;
}
.channel-icon-button:hover:not(:disabled) {
  background: var(--primary-soft);
  color: var(--primary);
}
.channel-icon-button:disabled {
  opacity: 0.45;
  cursor: default;
}
.channel-icon-primary {
  background: var(--primary);
  border-color: var(--primary);
  color: white;
}
.channel-icon-primary:hover:not(:disabled) {
  background: var(--primary);
  color: white;
  filter: brightness(1.08);
}
.channel-icon-button:focus-visible,
.duration-switch:focus-visible {
  outline: 2px solid var(--primary);
  outline-offset: 3px;
}
.duration-filter-settings {
  margin: 0;
}
.duration-toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
}
.duration-limit-label {
  display: block;
  margin-bottom: 8px;
  font-size: 0.85rem;
}
.duration-settings-dialog .alert {
  margin-top: 16px;
}
.duration-switch {
  appearance: none;
  position: relative;
  width: 32px;
  height: 20px;
  margin: 0;
  border: 0;
  border-radius: 12px;
  background: var(--border);
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.15s;
}
.duration-switch::before {
  content: '';
  position: absolute;
  top: 3px;
  left: 3px;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  background: white;
  transition: transform 0.15s;
}
.duration-switch:checked {
  background: var(--primary);
}
.duration-switch:checked::before {
  transform: translateX(12px);
}
.duration-switch:disabled {
  opacity: 0.45;
  cursor: default;
}
.channel-alert {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.duration-settings-dialog,
.channel-dialog,
.video-preview-dialog,
.add-listening-dialog {
  width: min(420px, calc(100% - 32px));
  max-height: calc(100dvh - 48px);
  overflow-y: auto;
  margin: auto;
  padding: 20px;
  border: 1px solid var(--border);
  border-radius: 20px;
  background: var(--card);
  color: var(--text);
}
.duration-settings-dialog::backdrop,
.channel-dialog::backdrop,
.video-preview-dialog::backdrop,
.add-listening-dialog::backdrop {
  background: rgb(0 0 0 / 50%);
}
.channel-dialog-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 16px;
}
.channel-dialog-header h2 {
  font-size: 1.1rem;
  margin: 0;
}
.video-preview-dialog {
  width: min(900px, calc(100% - 32px));
}
.video-preview-dialog h2 {
  overflow-wrap: anywhere;
}
.video-preview-player {
  display: block;
  width: 100%;
  aspect-ratio: 16 / 9;
  border: 0;
  border-radius: 10px;
  background: #000;
}
.video-preview-description {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  line-height: 1.6;
  margin: 16px 0 0;
}
.channel-add {
  display: flex;
  align-items: center;
  gap: 8px;
}
.channel-add input {
  flex: 1;
  min-width: 0;
}
.saved-channels {
  display: grid;
  gap: 4px;
  margin-top: 20px;
}
.channel-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 0;
  border-bottom: 1px solid var(--border);
}
.channel-row:last-child {
  border-bottom: 0;
}
.channel-row img {
  border-radius: 50%;
}
.channel-row span {
  flex: 1;
  min-width: 0;
  overflow-wrap: anywhere;
  font-size: 0.85rem;
}
.add-listening-dialog p {
  margin-bottom: 20px;
  overflow-wrap: anywhere;
}
.add-listening-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 20px;
}
.channel-video-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr));
  grid-auto-rows: 1fr;
  align-items: stretch;
  gap: 18px;
}
.channel-video-card {
  min-width: 0;
  border: 1px solid var(--border);
  border-radius: 16px;
  overflow: hidden;
  background: var(--card);
  display: flex;
  flex-direction: column;
}
.channel-video-thumb {
  width: 100%;
  padding: 0;
  border: 0;
  cursor: pointer;
  flex-shrink: 0;
  display: block;
  position: relative;
  aspect-ratio: 16 / 9;
  overflow: hidden;
  background: var(--bg);
}
.channel-video-title {
  padding: 0;
  border: 0;
  background: transparent;
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
}
.channel-video-thumb:focus-visible,
.channel-video-title:focus-visible {
  outline: 2px solid var(--primary);
  outline-offset: -2px;
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
  display: inline-flex;
  align-items: center;
  gap: 4px;
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
  gap: 10px;
  flex: 1;
}
.channel-video-meta {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 0.75rem;
  margin: 0;
}
.channel-video-body h4 {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
  min-height: 3em;
  max-height: 3em;
  font-size: 0.95rem;
  line-height: 1.5;
  margin: 0 0 8px;
  overflow-wrap: anywhere;
}
.channel-video-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: auto;
}
.channel-open-listening {
  color: var(--primary);
}
.channel-pagination {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  margin-top: 24px;
}
.spinning {
  animation: channel-spin 1s linear infinite;
}
@keyframes channel-spin {
  to {
    transform: rotate(360deg);
  }
}
@media (prefers-reduced-motion: reduce) {
  .spinning {
    animation: none;
  }
  .duration-switch,
  .duration-switch::before {
    transition: none;
  }
}
@media (max-width: 400px) {
  .channel-toolbar {
    gap: 6px;
  }
  .channel-toolbar .channel-icon-button {
    width: 36px;
  }
}
</style>
