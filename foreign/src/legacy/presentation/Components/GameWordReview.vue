<script setup lang="ts">
import { computed, ref } from 'vue'
import { Check, Flame, Leaf, RotateCcw, Sparkles } from 'lucide-vue-next'
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
  ].slice(0, 1),
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
    <div class="review-content">
      <div class="review-word-heading">
        <h2 class="review-word">{{ reveal.word }}</h2>
        <span v-if="reveal.phonetic" class="review-phonetic">{{
          reveal.phonetic
        }}</span>
      </div>
      <p class="review-definition">
        <span
          v-if="meaning?.part_of_speech || reveal.part_of_speech"
          class="review-pos"
          >{{ meaning?.part_of_speech || reveal.part_of_speech }}</span
        >
        {{ meaning?.definition || reveal.definition }}
      </p>
      <section class="review-examples" aria-label="Example for this meaning">
        <blockquote v-for="example in examples" :key="example">
          “{{ example }}”
        </blockquote>
        <p v-if="!examples.length" class="review-no-example">
          No example available.
        </p>
      </section>
    </div>
    <fieldset :disabled="form.processing || locked">
      <legend>Remember it?</legend>
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
          :title="level.hint"
          @click="rate(level.value)"
        >
          <component
            :is="level.icon"
            :size="17"
            :stroke-width="2.2"
            aria-hidden="true"
          />
          <strong>{{ level.label }}</strong>
          <Check
            v-if="selected === level.value"
            :size="15"
            class="memory-check"
            aria-hidden="true"
          />
        </button>
      </div>
    </fieldset>
    <p v-if="error" class="review-error" role="alert">{{ error }}</p>
    <p
      v-else-if="form.processing || locked"
      class="review-save-note"
      role="status"
    >
      {{ form.processing ? 'Saving…' : locked ? 'Loading…' : '' }}
    </p>
  </div>
</template>

<style scoped>
.game-word-review {
  display: flex;
  flex-direction: column;
  min-height: 0;
  padding: 16px;
  text-align: left;
}
.review-content {
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
  overflow-wrap: anywhere;
}
.review-word-heading {
  display: flex;
  align-items: baseline;
  flex-wrap: wrap;
  gap: 4px 10px;
}
.review-word {
  margin: 0;
  font-size: 28px;
  line-height: 1.2;
  letter-spacing: -0.04em;
}
.review-phonetic {
  color: var(--muted);
  font-size: 13px;
}
.review-definition {
  margin: 10px 0;
  font-size: 14px;
  line-height: 1.5;
}
.review-pos {
  display: inline;
  margin-right: 5px;
  font-size: 11px;
  font-weight: 700;
  color: #8b7cf8;
}
.review-examples {
  margin: 0 0 14px;
  padding: 9px 11px;
  border-radius: 10px;
  background: color-mix(in srgb, #8b7cf8 7%, transparent);
}
blockquote {
  margin: 0;
  font-size: 13px;
  line-height: 1.5;
  font-style: italic;
}
.review-no-example {
  margin: 0;
  color: var(--muted);
  font-size: 12px;
}
fieldset {
  flex-shrink: 0;
  min-width: 0;
  padding: 0;
  border: 0;
  margin: 0;
}
legend {
  padding: 0;
  margin-bottom: 8px;
  font-size: 12px;
  color: var(--muted);
  font-weight: 600;
}
.game-memory-levels {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}
.memory-card {
  --accent: #fb7185;
  position: relative;
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  border: 1px solid color-mix(in srgb, var(--accent) 38%, var(--border));
  border-radius: 11px;
  padding: 10px;
  background: color-mix(in srgb, var(--accent) 9%, var(--card));
  color: var(--text);
  text-align: left;
  font: inherit;
  cursor: pointer;
  transition:
    transform 150ms ease,
    box-shadow 150ms ease,
    background-color 150ms ease;
  animation: memory-card-in 220ms both;
  animation-delay: calc(var(--card-index) * 30ms);
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
.memory-card > svg {
  flex-shrink: 0;
  color: var(--accent);
}
.memory-card strong {
  font-size: 12px;
  line-height: 1.25;
  letter-spacing: -0.02em;
}
.memory-check {
  position: absolute;
  right: 6px;
  top: 4px;
}
@media (hover: hover) {
  .memory-card:hover:not(:disabled) {
    transform: translateY(-1px);
    background: color-mix(in srgb, var(--accent) 16%, var(--card));
  }
}
.memory-card:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}
.memory-card:active:not(:disabled) {
  transform: scale(0.97);
}
.memory-card.is-selected {
  border-color: var(--accent);
  box-shadow: 0 0 0 1px var(--accent);
  background: color-mix(in srgb, var(--accent) 18%, var(--card));
}
fieldset:disabled .memory-card.is-selected {
  animation: memory-saved 300ms ease-out both;
}
fieldset:disabled .memory-card:not(.is-selected) {
  opacity: 0.45;
}
.review-save-note {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}
.review-error {
  flex-shrink: 0;
  margin: 8px 0 0;
  color: var(--danger, #e45367);
  font-size: 12px;
}
@keyframes memory-card-in {
  from {
    opacity: 0;
    transform: translateY(4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
@keyframes memory-saved {
  50% {
    transform: scale(1.02);
  }
  to {
    transform: scale(1);
  }
}
@media (max-height: 500px) {
  .game-word-review {
    padding: 12px;
  }
  .review-word {
    font-size: 24px;
  }
  .review-definition {
    margin: 6px 0;
  }
  .review-examples {
    margin-bottom: 10px;
  }
  .game-memory-levels {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 6px;
  }
  .memory-card {
    flex-direction: column;
    gap: 4px;
    padding: 7px 4px;
    text-align: center;
  }
  .memory-card strong {
    font-size: 11px;
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
