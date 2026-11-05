<script setup lang="ts">
import { computed, ref } from 'vue'
import {
  BookOpen,
  Check,
  Flame,
  Leaf,
  RotateCcw,
  Sparkles,
} from 'lucide-vue-next'
import type { Reveal } from '@/domain/models'
import { useForm } from '@/application/navigation'
import { appPath } from '@/path'

const props = defineProps<{
  reveal: Reveal
  mode: 'quiz' | 'scramble' | 'wordle' | 'hangman' | 'word-search'
  locked?: boolean
}>()
const emit = defineEmits<{ rated: [id: number, level: number] }>()
const levels = [
  {
    value: 1,
    label: 'Forgot it',
    hint: 'Bring it back soon',
    icon: RotateCcw,
    color: 'coral',
  },
  {
    value: 2,
    label: 'Still learning',
    hint: 'A little more practice',
    icon: Flame,
    color: 'amber',
  },
  {
    value: 3,
    label: 'Almost there',
    hint: 'Review less often',
    icon: Leaf,
    color: 'sky',
  },
  {
    value: 4,
    label: 'Know it',
    hint: 'Give me a bigger break',
    icon: Sparkles,
    color: 'mint',
  },
]
const selected = ref<number | null>(null)
const error = ref('')
const form = useForm({ vocabulary_id: 0, level: 0 })
const meaning = computed(() => props.reveal.meanings?.[0])
const examples = computed(() =>
  [
    ...new Set(
      [meaning.value?.example, ...(meaning.value?.examples ?? [])]
        .filter(
          (value): value is string =>
            typeof value === 'string' && !!value.trim(),
        )
        .map((value) => value.trim()),
    ),
  ].slice(0, 2),
)

function rate(level: number): void {
  if (form.processing || props.locked || !props.reveal.vocabulary_id) return
  form.vocabulary_id = props.reveal.vocabulary_id
  form.level = level
  selected.value = level
  error.value = ''
  const path =
    props.mode === 'quiz' ? '/home/quiz' : `/home/puzzle/${props.mode}`
  void form.post(appPath(`${path}/rate`), {
    stayOnPage: true,
    background: true,
    onSuccess: () => emit('rated', props.reveal.vocabulary_id!, level),
    onError: () => {
      selected.value = null
      error.value =
        'Could not save your memory level. Pick a card to try again.'
    },
  })
}
</script>

<template>
  <div class="game-word-review">
    <div class="review-word-heading">
      <div>
        <p class="review-eyebrow">THE WORD TO KEEP</p>
        <h2 class="review-word">{{ reveal.word }}</h2>
        <p v-if="reveal.phonetic" class="review-phonetic">
          {{ reveal.phonetic }}
        </p>
      </div>
      <span class="review-book" aria-hidden="true"
        ><BookOpen :size="26"
      /></span>
    </div>
    <div class="review-definition">
      <span
        v-if="meaning?.part_of_speech || reveal.part_of_speech"
        class="review-pos"
      >
        {{ meaning?.part_of_speech || reveal.part_of_speech }}
      </span>
      <p>{{ meaning?.definition || reveal.definition }}</p>
    </div>
    <section class="review-examples" aria-label="Examples for this meaning">
      <p class="review-eyebrow"><span aria-hidden="true">“</span> IN CONTEXT</p>
      <blockquote v-for="example in examples" :key="example">
        {{ example }}
      </blockquote>
      <p v-if="!examples.length" class="review-no-example">
        No example is saved for this meaning yet.
      </p>
    </section>
    <fieldset :disabled="form.processing || locked">
      <legend>How well did you know it?</legend>
      <p class="review-instruction">Pick a memory card to continue.</p>
      <div class="game-memory-levels">
        <button
          v-for="(level, index) in levels"
          :key="level.value"
          type="button"
          class="memory-card"
          :class="[
            `memory-card--${level.color}`,
            { 'is-selected': selected === level.value },
          ]"
          :style="{ '--card-index': index }"
          :aria-pressed="selected === level.value"
          @click="rate(level.value)"
        >
          <span class="memory-card-top">
            <component :is="level.icon" :size="21" :stroke-width="2.2" />
            <Check v-if="selected === level.value" :size="18" />
            <span v-else class="memory-card-level">{{ level.value }} / 4</span>
          </span>
          <strong>{{ level.label }}</strong>
          <span class="memory-card-hint">{{ level.hint }}</span>
          <span class="memory-card-meter" aria-hidden="true">
            <i
              v-for="bar in 4"
              :key="bar"
              :class="{ 'is-filled': bar <= level.value }"
            />
          </span>
        </button>
      </div>
    </fieldset>
    <p v-if="error" class="review-error" role="alert">{{ error }}</p>
    <p v-else class="review-save-note" role="status">
      {{
        form.processing
          ? 'Saving your progress…'
          : locked
            ? 'Nice. Your next challenge is on its way…'
            : 'Your choice shapes what you practice next.'
      }}
    </p>
  </div>
</template>

<style scoped>
.game-word-review {
  padding: 26px 30px 22px;
  text-align: left;
}
.review-word-heading {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}
.review-eyebrow {
  margin: 0 0 8px;
  color: var(--muted);
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.16em;
}
.review-word {
  margin: 0;
  font-size: clamp(32px, 7vw, 46px);
  line-height: 1.12;
  letter-spacing: -0.045em;
  overflow-wrap: anywhere;
}
.review-phonetic {
  margin: 8px 0 0;
  color: var(--muted);
  font-size: 15px;
}
.review-book {
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  border-radius: 18px;
  flex-shrink: 0;
  color: #8b7cf8;
  background: color-mix(in srgb, #8b7cf8 12%, transparent);
  transform: rotate(8deg);
}
.review-definition {
  margin-top: 20px;
}
.review-pos {
  display: inline-block;
  border-radius: 6px;
  padding: 3px 8px;
  font-size: 11px;
  font-weight: 700;
  color: #8b7cf8;
  background: color-mix(in srgb, #8b7cf8 10%, transparent);
}
.review-definition p {
  margin: 8px 0 0;
  font-size: 16px;
  line-height: 1.6;
}
.review-examples {
  margin: 18px 0 24px;
  border: 1px solid color-mix(in srgb, #8b7cf8 18%, var(--border));
  border-radius: 14px;
  padding: 14px 16px;
  background: color-mix(in srgb, #8b7cf8 5%, transparent);
}
.review-examples .review-eyebrow {
  color: #8b7cf8;
  display: flex;
  align-items: center;
  gap: 6px;
}
.review-examples .review-eyebrow span {
  font-size: 24px;
  line-height: 10px;
}
blockquote {
  margin: 8px 0 0;
  font-size: 14px;
  line-height: 1.65;
  font-style: italic;
}
.review-no-example {
  margin: 0;
  color: var(--muted);
  font-size: 13px;
  line-height: 1.5;
}
fieldset {
  min-width: 0;
  padding: 0;
  border: 0;
  margin: 0;
}
legend {
  padding: 0;
  font-size: 18px;
  font-weight: 800;
  letter-spacing: -0.025em;
}
.review-instruction {
  margin: 5px 0 14px;
  font-size: 12px;
  color: var(--muted);
}
.game-memory-levels {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}
.memory-card {
  --accent: #fb7185;
  display: flex;
  flex-direction: column;
  gap: 7px;
  border: 1px solid color-mix(in srgb, var(--accent) 38%, var(--border));
  border-radius: 16px;
  padding: 14px;
  background: color-mix(in srgb, var(--accent) 9%, var(--card));
  color: var(--text);
  text-align: left;
  font: inherit;
  cursor: pointer;
  transition:
    transform 180ms ease,
    box-shadow 180ms ease,
    background-color 180ms ease;
  animation: memory-card-in 400ms both;
  animation-delay: calc(100ms + var(--card-index) * 55ms);
}
.memory-card--amber {
  --accent: #f5b942;
}
.memory-card--sky {
  --accent: #38bdf8;
}
.memory-card--mint {
  --accent: #34d399;
}
.memory-card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  color: var(--accent);
}
.memory-card-level {
  font-size: 10px;
  font-weight: 800;
  opacity: 0.8;
}
.memory-card strong {
  font-size: 14px;
  letter-spacing: -0.02em;
}
.memory-card-hint {
  color: var(--muted);
  font-size: 11px;
  line-height: 1.4;
}
.memory-card-meter {
  display: flex;
  gap: 4px;
  margin-top: 4px;
}
.memory-card-meter i {
  height: 4px;
  flex: 1;
  border-radius: 4px;
  background: color-mix(in srgb, var(--accent) 17%, transparent);
}
.memory-card-meter .is-filled {
  background: var(--accent);
}
@media (hover: hover) {
  .memory-card:hover:not(:disabled) {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px color-mix(in srgb, var(--accent) 18%, transparent);
    background: color-mix(in srgb, var(--accent) 16%, var(--card));
  }
}
.memory-card:focus-visible {
  outline: 3px solid var(--accent);
  outline-offset: 3px;
}
.memory-card:active:not(:disabled) {
  transform: scale(0.97);
}
.memory-card.is-selected {
  border-color: var(--accent);
  box-shadow: 0 0 0 2px var(--accent);
  background: color-mix(in srgb, var(--accent) 18%, var(--card));
}
fieldset:disabled .memory-card.is-selected {
  animation: memory-saved 420ms ease-out both;
}
@keyframes memory-saved {
  50% {
    transform: scale(1.035);
  }
  to {
    transform: scale(1);
  }
}
fieldset:disabled .memory-card:not(.is-selected) {
  opacity: 0.45;
}
.review-save-note {
  min-height: 16px;
  margin: 16px 0 0;
  text-align: center;
  color: var(--muted);
  font-size: 11px;
}
.review-error {
  margin: 14px 0 0;
  color: var(--danger, #e45367);
  font-size: 13px;
}
@keyframes memory-card-in {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
@media (max-width: 480px) {
  .game-word-review {
    padding: 22px 20px 18px;
  }
  .memory-card {
    padding: 12px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .memory-card,
  fieldset:disabled .memory-card.is-selected {
    animation: none;
    transition: none;
  }
  .memory-card:hover:not(:disabled) {
    transform: none;
  }
}
</style>
