<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { Reveal } from '@/domain/models'
import { useForm } from '@/application/navigation'
import { appPath } from '@/path'

const props = defineProps<{
  reveal: Reveal
  mode: 'quiz' | 'scramble' | 'wordle' | 'hangman' | 'word-search'
}>()

const levels = [
  { value: 1, label: 'Chưa nhớ', hint: 'Ôn lại thường xuyên' },
  { value: 2, label: 'Nhớ mang máng', hint: 'Ôn lại nhiều hơn' },
  { value: 3, label: 'Khá nhớ', hint: 'Ôn lại thưa hơn' },
  { value: 4, label: 'Nhớ rõ', hint: 'Giảm ôn trong thời gian gần' },
]
const selected = ref<number | null>(null)
const status = ref('')
const form = useForm({ vocabulary_id: 0, level: 0 })
watch(
  () => [props.reveal.vocabulary_id, props.reveal.memory_level],
  () => {
    selected.value = props.reveal.memory_level ?? null
    status.value = ''
  },
  { immediate: true },
)
const meaning = computed(() => props.reveal.meanings?.[0])
const examples = computed(() => {
  const values = [meaning.value?.example, ...(meaning.value?.examples ?? [])]
  return [
    ...new Set(values.filter((value): value is string => !!value?.trim())),
  ].slice(0, 2)
})

function rate(level: number): void {
  if (form.processing || !props.reveal.vocabulary_id) return
  form.vocabulary_id = props.reveal.vocabulary_id
  form.level = level
  status.value = ''
  const path =
    props.mode === 'quiz' ? '/home/quiz' : `/home/puzzle/${props.mode}`
  void form.post(appPath(`${path}/rate`), {
    stayOnPage: true,
    onSuccess: () => {
      selected.value = level
      status.value = 'Đã lưu mức ghi nhớ.'
    },
    onError: () => {
      status.value = 'Chưa lưu được. Vui lòng thử lại.'
    },
  })
}
</script>

<template>
  <section
    class="card puzzle-reveal-card game-word-review"
    :aria-label="`Review ${reveal.word}`"
  >
    <p class="card-title">{{ reveal.word }}</p>
    <p v-if="reveal.phonetic" class="card-subtitle">{{ reveal.phonetic }}</p>
    <p class="game-word-meaning">
      <span
        v-if="meaning?.part_of_speech || reveal.part_of_speech"
        class="muted"
      >
        {{ meaning?.part_of_speech || reveal.part_of_speech }} ·
      </span>
      {{ meaning?.definition || reveal.definition }}
    </p>
    <blockquote v-for="example in examples" :key="example">
      {{ example }}
    </blockquote>
    <fieldset v-if="reveal.vocabulary_id" :disabled="form.processing">
      <legend>Bạn nhớ từ này đến đâu?</legend>
      <div class="game-memory-levels">
        <button
          v-for="level in levels"
          :key="level.value"
          type="button"
          class="btn btn-sm btn-secondary"
          :class="{ 'is-selected': selected === level.value }"
          :aria-pressed="selected === level.value"
          :title="level.hint"
          @click="rate(level.value)"
        >
          {{ level.label }}
        </button>
      </div>
    </fieldset>
    <p
      v-if="status || form.processing"
      class="game-memory-status muted"
      role="status"
    >
      {{ form.processing ? 'Đang lưu…' : status }}
    </p>
  </section>
</template>

<style scoped>
.game-word-review {
  text-align: left;
}
.game-word-meaning {
  line-height: 1.6;
  margin: 12px 0;
}
blockquote {
  margin: 8px 0;
  padding-left: 12px;
  border-left: 3px solid var(--primary, #4361ee);
  line-height: 1.5;
  font-style: italic;
}
fieldset {
  min-width: 0;
  border: 0;
  padding: 0;
  margin: 16px 0 0;
}
legend {
  padding: 0;
  margin-bottom: 8px;
  font-weight: 600;
}
.game-memory-levels {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.game-memory-levels .is-selected {
  background: var(--primary, #4361ee);
  border-color: var(--primary, #4361ee);
  color: white;
}
.game-memory-status {
  font-size: 13px;
  margin: 8px 0 0;
}
</style>
