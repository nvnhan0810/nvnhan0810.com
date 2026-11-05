<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { ArrowUpRight, Sparkles, Trophy, X } from 'lucide-vue-next'
import type { Reveal } from '@/domain/models'
import { Link, useForm } from '@/application/navigation'
import { RoutePath } from '../../../app/router/paths'
import { appPath } from '@/path'
import GameWordReview from './GameWordReview.vue'

const props = withDefaults(
  defineProps<{
    reviews: readonly Reveal[]
    mode: 'quiz' | 'scramble' | 'wordle' | 'hangman' | 'word-search'
    resolved: boolean
    wasCorrect?: boolean | null
    score?: number
    record?: number | string | null
  }>(),
  { wasCorrect: null, score: 0 },
)

const dialog = ref<HTMLDialogElement | null>(null)
const ratedIds = ref(new Set<number>())
const advancing = ref(false)
const nextError = ref(false)
const nextForm = useForm({})
let advanceTimer: ReturnType<typeof setTimeout> | undefined
let previousFocus: HTMLElement | null = null
let previousOverflow: string | undefined
const pending = computed(() =>
  props.reviews.filter(
    (review) =>
      !review.reviewed && !ratedIds.value.has(review.vocabulary_id ?? -1),
  ),
)
const current = computed(() => pending.value[0])
const displayedReview = computed(
  () =>
    current.value ??
    (advancing.value && props.resolved ? props.reviews.at(-1) : undefined),
)
const visible = computed(
  () => !!current.value || advancing.value || nextError.value,
)
const isWin = computed(() => props.wasCorrect !== false)
const title = computed(() =>
  props.mode === 'word-search' && !props.resolved
    ? 'Word found!'
    : props.record != null
      ? 'New personal best!'
      : isWin.value
        ? 'Nicely done!'
        : 'Keep going!',
)

function restorePage(): void {
  if (previousOverflow !== undefined) {
    document.documentElement.style.overflow = previousOverflow
    previousOverflow = undefined
  }
}
watch(
  visible,
  async (open) => {
    await nextTick()
    const element = dialog.value
    if (open && element) {
      previousFocus = document.activeElement as HTMLElement | null
      previousOverflow ??= document.documentElement.style.overflow
      document.documentElement.style.overflow = 'hidden'
      if (!element.open) element.showModal?.()
      element.focus()
    } else {
      element?.close?.()
      restorePage()
      if (previousFocus?.isConnected) previousFocus.focus()
    }
  },
  { immediate: true, flush: 'post' },
)

watch(
  () => displayedReview.value?.vocabulary_id,
  async () => {
    await nextTick()
    if (dialog.value) {
      dialog.value.scrollTop = 0
      dialog.value.focus()
    }
  },
)

function loadNext(): void {
  if (nextForm.processing) return
  advancing.value = true
  nextError.value = false
  const prefix =
    props.mode === 'quiz' ? '/home/quiz' : `/home/puzzle/${props.mode}`
  void nextForm.post(appPath(`${prefix}/next`), {
    background: true,
    onError: () => {
      advancing.value = false
      nextError.value = true
    },
  })
}
function onRated(id: number): void {
  advancing.value = true
  advanceTimer = setTimeout(
    () => {
      ratedIds.value = new Set([...ratedIds.value, id])
      if (pending.value.length) {
        advancing.value = false
      } else if (props.resolved) {
        loadNext()
      } else {
        advancing.value = false
      }
    },
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ? 0 : 420,
  )
}
// A refreshed completed round may already have all its ratings saved.
watch(
  () =>
    props.resolved &&
    props.reviews.length > 0 &&
    props.reviews.every((review) => review.reviewed),
  (ready) => {
    if (ready) loadNext()
  },
  { immediate: true },
)
onBeforeUnmount(() => {
  clearTimeout(advanceTimer)
  restorePage()
})
</script>

<template>
  <Teleport to="body">
    <dialog
      v-if="visible"
      ref="dialog"
      class="game-result-dialog"
      :class="{ 'is-win': isWin, 'is-advancing': advancing && !nextError }"
      aria-modal="true"
      aria-labelledby="game-result-title"
      tabindex="-1"
      @cancel.prevent
    >
      <header class="result-hero">
        <div v-if="isWin" class="result-sparks" aria-hidden="true">
          <i v-for="spark in 10" :key="spark" :style="{ '--spark': spark }" />
        </div>
        <span class="result-outcome-icon" aria-hidden="true">
          <Trophy v-if="isWin" :size="18" /><Sparkles v-else :size="18" />
        </span>
        <h1 id="game-result-title">{{ title }}</h1>
        <span class="result-score" :aria-label="`${score} correct this run`"
          >✓ {{ score }}</span
        >
        <Link
          :href="appPath(RoutePath.games)"
          class="result-exit"
          aria-label="Leave game"
          ><X :size="18"
        /></Link>
      </header>
      <GameWordReview
        v-if="displayedReview"
        :key="displayedReview.vocabulary_id ?? displayedReview.word"
        :reveal="displayedReview"
        :mode="mode"
        :locked="advancing"
        @rated="onRated"
      />
      <div v-else class="result-loading" role="status">
        <template v-if="nextError">
          <p>
            Your memory level is saved. We couldn't load the next challenge.
          </p>
          <button type="button" class="result-retry" @click="loadNext">
            Try again <ArrowUpRight :size="16" />
          </button>
        </template>
        <template v-else
          ><Sparkles :size="24" />
          <p>Preparing your next challenge…</p></template
        >
      </div>
    </dialog>
  </Teleport>
</template>

<style scoped>
.game-result-dialog {
  width: min(420px, calc(100vw - 24px));
  max-height: calc(100dvh - 32px);
  box-sizing: border-box;
  margin: auto;
  padding: 0;
  overflow: hidden;
  border: 1px solid color-mix(in srgb, #a99bff 24%, var(--border));
  border-radius: 20px;
  color: var(--text);
  background: var(--card);
  box-shadow: 0 20px 70px #080b2d55;
  animation: result-pop 280ms cubic-bezier(0.16, 1, 0.3, 1) both;
}
.game-result-dialog[open] {
  display: flex;
  flex-direction: column;
}
.game-result-dialog::backdrop {
  background: #080b2599;
  backdrop-filter: blur(5px);
}
.game-result-dialog:focus {
  outline: none;
}
.result-hero {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  display: flex;
  align-items: center;
  gap: 9px;
  flex-shrink: 0;
  padding: 8px 10px 8px 16px;
  color: #fff;
  background: linear-gradient(115deg, #373066, #51418a);
}
.result-outcome-icon {
  display: grid;
  place-items: center;
  flex-shrink: 0;
  color: #d1c6ff;
}
.is-win .result-outcome-icon {
  color: #ffe298;
}
.result-hero h1 {
  margin: 0;
  flex: 1;
  font-size: 15px;
  line-height: 1.3;
  letter-spacing: -0.025em;
  font-weight: 750;
}
.result-score {
  font-size: 11px;
  color: #e3dff8;
  white-space: nowrap;
}
.result-exit {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  color: #e4deff;
  background: #ffffff12;
  flex-shrink: 0;
}
.result-exit:hover {
  background: #ffffff2b;
}
.result-exit:focus-visible {
  outline: 2px solid white;
  outline-offset: 2px;
}
.result-sparks {
  position: absolute;
  inset: 0;
  pointer-events: none;
  z-index: -1;
}
.result-sparks i {
  position: absolute;
  left: calc(var(--spark) * 9%);
  top: -8px;
  width: 4px;
  height: 6px;
  border-radius: 2px;
  background: #ffe298;
  opacity: 0;
  animation: result-confetti 1s ease-out both;
  animation-delay: calc(var(--spark) * 35ms);
}
.result-sparks i:nth-child(2n) {
  background: #a998ff;
}
.result-sparks i:nth-child(3n) {
  background: #76e0cf;
}
.result-loading {
  padding: 24px 18px;
  display: grid;
  justify-items: center;
  text-align: center;
  gap: 10px;
  overflow-y: auto;
  color: var(--muted);
  font-size: 13px;
}
.result-retry {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 0;
  min-height: 44px;
  padding: 10px 16px;
  background: #7965dd;
  color: white;
  font: inherit;
  font-weight: 700;
  border-radius: 10px;
  cursor: pointer;
}
@keyframes result-pop {
  from {
    opacity: 0;
    transform: translateY(12px) scale(0.97);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
@keyframes result-confetti {
  10% {
    opacity: 0.7;
  }
  to {
    opacity: 0;
    transform: translateY(70px) rotate(160deg);
  }
}
@media (prefers-reduced-motion: reduce) {
  .game-result-dialog,
  .result-sparks i {
    animation: none;
  }
}
</style>
