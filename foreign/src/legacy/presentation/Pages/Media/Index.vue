<script setup lang="ts">
import { RoutePath } from '../../../../app/router/paths'
import { durationLabel } from '../../../../modules/foreign/presentation/duration'
import { Youtube } from 'lucide-vue-next'

import { apiFetch } from '@/application/navigation'
import type { PropType } from 'vue'
import type { MediaSummary } from '@/domain/models'
import { appPath } from '@/path'
import { Head, Link, useForm } from '@/application/navigation'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  items: { type: Array as PropType<MediaSummary[]>, default: () => [] },
})

const filter = ref('all')
const url = ref('')
const fetching = ref(false)
const status = ref('')
const statusError = ref(false)
const preview = ref<Partial<MediaSummary> | null>(null)
const openMenuId = ref<number | null>(null)
const addOpen = ref(false)

const form = useForm({
  url: '',
  title: '',
  frequency: 'weekly',
  difficulty: 'intermediate',
})

const filteredItems = computed(() => {
  if (filter.value === 'all') return props.items
  return props.items.filter((item) => item.difficulty === filter.value)
})

function difficultyLabel(value: string) {
  if (value === 'beginner') return 'Beginner'
  if (value === 'advanced') return 'Advanced'
  return 'Intermediate'
}

function resetAddForm() {
  preview.value = null
  url.value = ''
  fetching.value = false
  status.value = ''
  statusError.value = false
  form.reset()
  form.clearErrors()
  form.frequency = 'weekly'
  form.difficulty = 'intermediate'
}

function openAddModal() {
  resetAddForm()
  addOpen.value = true
}

function closeAddModal() {
  if (form.processing || fetching.value) return
  addOpen.value = false
  resetAddForm()
}

async function fetchPreview() {
  const value = url.value.trim()
  if (!value || fetching.value) return

  fetching.value = true
  statusError.value = false
  status.value = ''
  preview.value = null

  try {
    const res = await apiFetch('/media/preview', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
      },
      credentials: 'same-origin',
      body: JSON.stringify({ url: value }),
    })
    const payload = await res.json().catch(() => ({}))
    if (!res.ok) {
      throw new Error(
        payload.error || payload.message || 'Could not fetch preview.',
      )
    }
    const data = payload.data || {}
    preview.value = data
    form.url = data.url || value
    form.title = data.title || ''
    status.value = 'Preview ready — edit title if needed, then Save.'
  } catch (error) {
    statusError.value = true
    status.value =
      error instanceof Error ? error.message : 'Could not fetch preview.'
  } finally {
    fetching.value = false
  }
}

function submit() {
  form.post(appPath('/home/media/youtube'), {
    onSuccess: () => {
      addOpen.value = false
      resetAddForm()
    },
  })
}

function destroyMedia(item: MediaSummary) {
  if (!window.confirm(`Remove "${item.title}" from your list?`)) return
  openMenuId.value = null
  useForm({}).delete(appPath(`/home/media/${item.id}`))
}

function onDocClick(event: MouseEvent) {
  if (
    !(event.target instanceof Element) ||
    !event.target.closest('.action-menu')
  ) {
    openMenuId.value = null
  }
}

watch(addOpen, (open) => {
  document.body.classList.toggle('media-add-modal-open', open)
})

onMounted(() => document.addEventListener('click', onDocClick))
onUnmounted(() => {
  document.removeEventListener('click', onDocClick)
  document.body.classList.remove('media-add-modal-open')
})
</script>

<template>
  <Head title="Listen" />
  <AppLayout title="Listen" heading="Listen">
    <template #header>
      <header class="user-header media-header">
        <span class="user-header-spacer" aria-hidden="true" />
        <h1>Listen</h1>
        <div class="media-header-actions">
          <Link
            :href="RoutePath.channels"
            class="user-header-action"
            aria-label="YouTube channels"
            title="YouTube channels"
          >
            <Youtube :size="22" aria-hidden="true" />
          </Link>
          <button
            type="button"
            class="user-header-action"
            aria-label="Add from YouTube"
            @click="openAddModal"
          >
            +
          </button>
        </div>
      </header>
    </template>

    <div v-if="items.length === 0" class="empty-state">
      No media yet. Tap + to add a YouTube video.
    </div>
    <template v-else>
      <div class="media-filters" role="group" aria-label="Filter by difficulty">
        <button
          v-for="choice in ['all', 'beginner', 'intermediate', 'advanced']"
          :key="choice"
          type="button"
          class="media-filter"
          :class="{ active: filter === choice }"
          @click="filter = choice"
        >
          {{ choice === 'all' ? 'All' : difficultyLabel(choice) }}
        </button>
      </div>

      <p v-show="filteredItems.length === 0" class="media-filter-empty muted">
        No media at this difficulty.
      </p>

      <div class="media-list listening-video-grid">
        <div
          v-for="item in filteredItems"
          :key="item.id"
          class="list-item media-list-item listening-video-card"
        >
          <Link
            :href="
              appPath(RoutePath.mediaDetail.replace(':id', String(item.id)))
            "
            class="media-list-item-link"
          >
            <div class="listening-video-thumb">
              <img
                v-if="item.thumbnail_url || item.source_id"
                :src="
                  item.thumbnail_url ||
                  `https://i.ytimg.com/vi/${item.source_id}/hqdefault.jpg`
                "
                :alt="item.title"
                loading="lazy"
              />
              <span v-else class="listening-video-placeholder">▶</span>
              <span
                v-if="durationLabel(item.duration)"
                class="listening-video-duration"
                :aria-label="`Duration ${durationLabel(item.duration)}`"
                >{{ durationLabel(item.duration) }}</span
              >
            </div>
            <div class="list-item-body">
              <p class="title" :title="item.title">{{ item.title }}</p>
              <p class="subtitle">
                <span
                  class="difficulty-tag"
                  :class="`difficulty-tag--${item.difficulty || 'intermediate'}`"
                  >{{
                    item.difficulty_label || difficultyLabel(item.difficulty)
                  }}</span
                >
                · {{ String(item.type || '').toUpperCase() }}
              </p>
            </div>
          </Link>
          <div class="action-menu">
            <button
              type="button"
              class="btn-icon action-menu-trigger"
              aria-label="Options"
              aria-haspopup="true"
              @click.stop="openMenuId = openMenuId === item.id ? null : item.id"
            >
              ⋮
            </button>
            <div class="action-menu-panel" :hidden="openMenuId !== item.id">
              <button
                type="button"
                class="action-menu-danger"
                @click="destroyMedia(item)"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div
      class="media-add-modal"
      :class="{ 'is-open': addOpen }"
      :hidden="!addOpen"
    >
      <div class="media-add-modal-backdrop" aria-hidden="true" />
      <div
        class="media-add-modal-card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="media-add-modal-title"
      >
        <div class="media-add-modal-head">
          <h2 id="media-add-modal-title" class="media-add-modal-title">
            Add from YouTube
          </h2>
          <button
            type="button"
            class="media-add-modal-close"
            aria-label="Close"
            :disabled="form.processing || fetching"
            @click="closeAddModal"
          >
            ×
          </button>
        </div>

        <div class="media-add-modal-body">
          <p class="muted media-add-modal-hint">
            Paste a YouTube link (watch, youtu.be, or Shorts), fetch the title,
            then save.
          </p>

          <div class="form-group">
            <label for="youtube-url">YouTube URL</label>
            <div class="youtube-add-row">
              <input
                id="youtube-url"
                v-model="url"
                type="url"
                class="form-control"
                placeholder="https://www.youtube.com/watch?v=..."
                autocomplete="off"
                :disabled="form.processing"
              />
              <button
                type="button"
                class="btn btn-secondary"
                :disabled="fetching || form.processing"
                @click="fetchPreview"
              >
                Fetch
              </button>
            </div>
            <p
              v-if="status"
              class="youtube-add-status muted"
              :style="statusError ? 'color:var(--danger, #c1121f)' : undefined"
            >
              {{ status }}
            </p>
          </div>

          <div v-if="preview" class="youtube-preview">
            <form class="flc-form-submit" @submit.prevent="submit">
              <div class="youtube-preview-body">
                <img
                  v-if="preview.thumbnail_url"
                  :src="preview.thumbnail_url"
                  :alt="preview.title || ''"
                  class="youtube-preview-thumb"
                />
                <div class="youtube-preview-fields">
                  <div class="form-group" style="margin-bottom: 12px">
                    <label for="youtube-title">Title</label>
                    <input
                      id="youtube-title"
                      v-model="form.title"
                      type="text"
                      class="form-control"
                      required
                      maxlength="255"
                      :disabled="form.processing"
                    />
                  </div>
                  <div class="youtube-preview-meta">
                    <div class="form-group" style="margin-bottom: 0">
                      <label for="youtube-difficulty">Difficulty</label>
                      <select
                        id="youtube-difficulty"
                        v-model="form.difficulty"
                        class="form-control"
                        :disabled="form.processing"
                      >
                        <option value="beginner">Beginner</option>
                        <option value="intermediate">Intermediate</option>
                        <option value="advanced">Advanced</option>
                      </select>
                    </div>
                  </div>
                </div>
              </div>
              <button
                type="submit"
                class="btn btn-block"
                style="margin-top: 16px"
                :disabled="form.processing"
              >
                Save
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.listening-video-grid {
  display: grid;
  width: 100%;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 260px), 1fr));
  grid-auto-rows: 1fr;
  align-items: stretch;
  gap: 18px;
}
.listening-video-card {
  position: relative;
  display: flex;
  flex-direction: column;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 1px solid var(--border);
  border-radius: 16px;
  background: var(--card);
  overflow: visible;
}
.listening-video-card .media-list-item-link {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 0;
  min-width: 0;
  width: 100%;
}
.listening-video-thumb {
  flex-shrink: 0;
  position: relative;
  aspect-ratio: 16 / 9;
  border-radius: 15px 15px 0 0;
  overflow: hidden;
  background: var(--bg);
}
.listening-video-thumb img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.listening-video-placeholder {
  display: grid;
  height: 100%;
  place-items: center;
}
.listening-video-duration {
  position: absolute;
  right: 10px;
  bottom: 10px;
  padding: 3px 6px;
  border-radius: 4px;
  background: rgb(0 0 0 / 80%);
  color: white;
  font-size: 0.75rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}
.listening-video-card .list-item-body {
  padding: 16px 48px 16px 16px;
  min-width: 0;
}
.listening-video-card .title {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
  min-height: 3em;
  max-height: 3em;
  white-space: normal;
  overflow-wrap: anywhere;
  font-size: 0.95rem;
  line-height: 1.5;
}
.listening-video-card .action-menu {
  position: absolute;
  right: 8px;
  bottom: 12px;
}

.media-header {
  grid-template-columns: 84px minmax(0, 1fr) 84px;
}

.media-header-actions {
  display: flex;
  gap: 4px;
}
</style>
