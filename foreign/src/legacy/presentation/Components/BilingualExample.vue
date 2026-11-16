<script setup lang="ts">
defineProps<{
  text: string
  translation?: string | undefined
  language?: 'en' | 'vi'
}>()
</script>

<template>
  <span class="bilingual-example">
    <span lang="en">“{{ text }}”</span>
    <small
      v-if="language === 'vi' && translation"
      lang="vi"
      class="example-translation"
      >{{ translation }}</small
    >
  </span>
</template>

<style scoped>
.bilingual-example {
  display: block;
}
.example-translation {
  display: block;
  margin-top: 4px;
  font-size: 0.85em;
  font-style: normal;
  font-weight: normal;
  line-height: 1.5;
  color: var(--text-muted, #64748b);
}
</style>
