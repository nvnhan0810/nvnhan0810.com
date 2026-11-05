<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import type { TranscriptSegment } from '@/domain/transcript-sync'

const props = defineProps<{
  segments: readonly TranscriptSegment[]
  activeIndex: number
}>()

const emit = defineEmits<{
  seek: [seconds: number]
}>()

const listRef = ref<HTMLElement | null>(null)
let scrollFrame: number | undefined

function stopScroll(): void {
  if (scrollFrame !== undefined) cancelAnimationFrame(scrollFrame)
  scrollFrame = undefined
}

function scrollToParagraph(list: HTMLElement, target: number): void {
  const start = list.scrollTop
  const distance = target - start
  if (
    Math.abs(distance) < 1 ||
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
  ) {
    list.scrollTop = target
    return
  }

  // Keep a consistent pace across browsers and continue from the current
  // position if playback moves to another paragraph during the animation.
  const duration = 600
  let startedAt: number | undefined
  const animate = (time: number): void => {
    startedAt ??= time
    const progress = Math.min((time - startedAt) / duration, 1)
    const eased = progress * progress * (3 - 2 * progress)
    list.scrollTop = start + distance * eased
    scrollFrame = progress < 1 ? requestAnimationFrame(animate) : undefined
  }
  scrollFrame = requestAnimationFrame(animate)
}

onBeforeUnmount(stopScroll)

watch(
  () => props.activeIndex,
  async (index, _previous, onCleanup) => {
    stopScroll()
    let cancelled = false
    onCleanup(() => {
      cancelled = true
      stopScroll()
    })
    if (index < 0) {
      return
    }

    await nextTick()
    if (cancelled) return
    const list = listRef.value
    const active = list?.querySelector<HTMLElement>(
      `[data-segment-index="${index}"]`,
    )
    if (!list || !active) return
    const listBounds = list.getBoundingClientRect()
    const activeBounds = active.getBoundingClientRect()
    const centeredTop =
      list.scrollTop +
      activeBounds.top -
      listBounds.top -
      list.clientTop +
      activeBounds.height / 2 -
      list.clientHeight / 2
    scrollToParagraph(
      list,
      Math.max(0, Math.min(centeredTop, list.scrollHeight - list.clientHeight)),
    )
  },
  { immediate: true },
)

function onSegmentClick(segment: TranscriptSegment): void {
  emit('seek', segment.start)
}

function formatTime(seconds: number): string {
  const total = Math.max(0, Math.floor(seconds))
  const mins = Math.floor(total / 60)
  const secs = total % 60
  return `${mins}:${secs.toString().padStart(2, '0')}`
}
</script>

<template>
  <div
    ref="listRef"
    class="transcript-timed"
    role="list"
    @wheel.passive="stopScroll"
    @touchstart.passive="stopScroll"
    @pointerdown="stopScroll"
    @keydown="stopScroll"
  >
    <button
      v-for="(segment, index) in segments"
      :key="`${segment.start}-${index}`"
      type="button"
      class="transcript-timed-line"
      :class="{ 'is-active': index === activeIndex }"
      :data-segment-index="index"
      role="listitem"
      @click="onSegmentClick(segment)"
    >
      <span class="transcript-timed-time">{{ formatTime(segment.start) }}</span>
      <span class="transcript-timed-text">{{ segment.text }}</span>
    </button>
  </div>
</template>
