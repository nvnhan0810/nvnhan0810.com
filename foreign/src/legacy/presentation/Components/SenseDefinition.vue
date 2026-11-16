<script setup lang="ts">
import { ref, watch } from 'vue'

const props = defineProps<{
  definition: string
  definitionVi?: string | null | undefined
}>()
const emit = defineEmits<{ 'language-change': [language: 'en' | 'vi'] }>()
const language = ref<'en' | 'vi'>('en')
watch(language, (value) => emit('language-change', value))
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
        type="button"
        class="btn btn-sm btn-secondary"
        :aria-pressed="language === 'vi'"
        :aria-label="
          language === 'en' ? 'Chuyển sang tiếng Việt' : 'Switch to English'
        "
        @click="language = language === 'en' ? 'vi' : 'en'"
      >
        {{ language === 'en' ? 'English' : 'Tiếng Việt' }}
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
    <slot :language="language" />
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
