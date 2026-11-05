<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { ArrowUpRight, Check, Sparkles, Trophy, X } from 'lucide-vue-next'
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
        : 'A word worth learning',
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
      aria-describedby="game-result-description"
      tabindex="-1"
      @cancel.prevent
    >
      <header class="result-hero">
        <div v-if="isWin" class="result-sparks" aria-hidden="true">
          <i v-for="spark in 10" :key="spark" :style="{ '--spark': spark }" />
        </div>
        <div class="result-topline">
          <span class="result-mode"
            ><span class="result-live-dot" />
            {{
              mode === 'quiz'
                ? 'VOCAB QUIZ'
                : mode.replace('-', ' ').toUpperCase()
            }}</span
          >
          <Link
            :href="appPath(RoutePath.games)"
            class="result-exit"
            aria-label="Leave game"
            ><X :size="18"
          /></Link>
        </div>
        <div class="result-outcome-icon" aria-hidden="true">
          <Trophy v-if="isWin" :size="28" /><Sparkles v-else :size="28" />
        </div>
        <h1 id="game-result-title">{{ title }}</h1>
        <p id="game-result-description">
          {{
            isWin
              ? 'One more word in your world.'
              : 'Take a moment. Make this one stick.'
          }}
        </p>
        <div class="result-chips">
          <span><Check :size="13" /> {{ score }} correct this run</span
          ><span><Sparkles :size="13" /> Keep growing</span>
        </div>
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
  width: min(560px, calc(100vw - 28px));
  max-height: min(92dvh, 960px);
  margin: auto;
  padding: 0;
  overflow-x: hidden;
  overflow-y: auto;
  overscroll-behavior: contain;
  border: 1px solid color-mix(in srgb, #a99bff 24%, var(--border));
  border-radius: 26px;
  color: var(--text);
  background: var(--card);
  box-shadow: 0 32px 100px #080b2d66;
  animation: result-pop 430ms cubic-bezier(0.16, 1, 0.3, 1) both;
}
.game-result-dialog::backdrop {
  background: #080b25b8;
  backdrop-filter: blur(8px);
  animation: result-backdrop 250ms ease-out both;
}
.game-result-dialog:focus {
  outline: none;
}
.result-hero {
  position: relative;
  isolation: isolate;
  overflow: hidden;
  padding: 18px 26px 22px;
  text-align: center;
  color: #fff;
  background:
    radial-gradient(ellipse at 15% 0%, #9572ff77, transparent 65%),
    linear-gradient(125deg, #282354, #35306d 55%, #273961);
}
.is-win .result-hero {
  background:
    radial-gradient(ellipse at 80% 0%, #7467ff99, transparent 60%),
    linear-gradient(125deg, #29234f, #40337f 60%, #244c69);
}
.result-topline {
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
  z-index: 1;
}
.result-mode {
  display: inline-flex;
  gap: 6px;
  align-items: center;
  font-size: 9px;
  letter-spacing: 0.15em;
  font-weight: 800;
  color: #ddd8ff;
}
.result-live-dot {
  width: 5px;
  height: 5px;
  background: #b9a5ff;
  border-radius: 50%;
  box-shadow: 0 0 10px #b9a5ff;
}
.result-exit {
  display: grid;
  place-items: center;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  color: #e4deff;
  background: #ffffff12;
  transition: background 150ms;
}
.result-exit:hover {
  background: #ffffff2b;
}
.result-exit:focus-visible {
  outline: 2px solid white;
  outline-offset: 3px;
}
.result-outcome-icon {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  border-radius: 20px;
  margin: 4px auto 12px;
  background: #ffffff12;
  color: #c7bbff;
  box-shadow: inset 0 0 0 1px #ffffff18;
  transform: rotate(-8deg);
}
.is-win .result-outcome-icon {
  color: #ffe298;
  background: #ffd36916;
  box-shadow:
    inset 0 0 0 1px #ffe29840,
    0 0 35px #ffc85912;
  animation: trophy-arrive 650ms cubic-bezier(0.16, 1, 0.3, 1) both;
}
.result-hero h1 {
  position: relative;
  margin: 0;
  font-size: clamp(24px, 5vw, 30px);
  letter-spacing: -0.04em;
  font-weight: 850;
}
.result-hero > p {
  margin: 7px 0 15px;
  font-size: 12px;
  color: #d3cfee;
}
.result-chips {
  display: flex;
  justify-content: center;
  gap: 8px;
  flex-wrap: wrap;
}
.result-chips span {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 5px 9px;
  border-radius: 20px;
  font-size: 10px;
  background: #ffffff0d;
  color: #e3dff8;
  border: 1px solid #ffffff14;
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
  width: 5px;
  height: 8px;
  border-radius: 2px;
  background: #ffe298;
  opacity: 0;
  animation: result-confetti 1.6s ease-out both;
  animation-delay: calc(var(--spark) * 45ms);
}
.result-sparks i:nth-child(2n) {
  background: #a998ff;
  width: 7px;
  height: 7px;
}
.result-sparks i:nth-child(3n) {
  background: #76e0cf;
}
.result-loading {
  padding: 36px 24px;
  display: grid;
  justify-items: center;
  text-align: center;
  gap: 12px;
  color: var(--muted);
  font-size: 14px;
}
.result-retry {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 0;
  padding: 12px 20px;
  background: #7965dd;
  color: white;
  font: inherit;
  font-weight: 700;
  border-radius: 12px;
  cursor: pointer;
}
@keyframes result-pop {
  from {
    opacity: 0;
    transform: translateY(26px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
@keyframes result-backdrop {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}
@keyframes trophy-arrive {
  from {
    transform: translateY(10px) rotate(-24deg) scale(0.6);
  }
  to {
    transform: translateY(0) rotate(-8deg) scale(1);
  }
}
@keyframes result-confetti {
  10% {
    opacity: 0.8;
  }
  to {
    opacity: 0;
    transform: translateY(230px) rotate(260deg);
  }
}
@media (max-width: 480px) {
  .game-result-dialog {
    border-radius: 22px;
    max-height: 94dvh;
  }
  .result-hero {
    padding: 14px 20px 18px;
  }
  .result-outcome-icon {
    width: 46px;
    height: 46px;
    margin-bottom: 10px;
    border-radius: 16px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .game-result-dialog,
  .game-result-dialog::backdrop,
  .result-outcome-icon,
  .is-win .result-outcome-icon,
  .result-sparks i {
    animation: none;
  }
}
</style>
