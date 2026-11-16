<script setup lang="ts">
import { ref, watch } from 'vue'

const props = defineProps<{
  definition: string
  definitionVi?: string | null | undefined
}>()
const language = ref<'en' | 'vi'>('en')
watch(
  () => props.definition,
  () => {
    language.value = 'en'
  },
)
</script>

<template>
  <div class="sense-definition">
    <div class="sense-language" role="group" aria-label="Definition language">
      <button
        v-for="option in [
          { value: 'en', label: 'English' },
          { value: 'vi', label: 'Tiếng Việt' },
        ] as const"
        :key="option.value"
        type="button"
        class="btn btn-sm btn-secondary"
        :aria-pressed="language === option.value"
        @click="language = option.value"
      >
        {{ option.label }}
      </button>
    </div>
    <p :lang="language === 'vi' && definitionVi?.trim() ? 'vi' : 'en'">
      {{
        language === 'vi' && definitionVi?.trim() ? definitionVi : definition
      }}
    </p>
    <p
      v-if="language === 'vi' && !definitionVi?.trim()"
      class="muted"
      lang="vi"
    >
      Chưa có giải thích tiếng Việt cho nghĩa này. Đang hiển thị tiếng Anh.
    </p>
  </div>
</template>

<style scoped>
.sense-language {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin: 8px 0;
}
.sense-language button[aria-pressed='true'] {
  border-color: currentColor;
  font-weight: 700;
}
.sense-definition p {
  margin: 4px 0;
}
</style>
