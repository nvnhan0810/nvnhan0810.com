<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from './Layouts/AppLayout.vue'
import { pageComponents } from '../app/page-components'
import { loadPage, page } from '../application/navigation'
const route = useRoute()
const component = computed(() => pageComponents[page.component])
const screenProps = computed(() =>
  Object.fromEntries(
    Object.entries(page.props).filter(
      ([key]) => !['auth', 'flash', 'errors', 'debtGoal'].includes(key),
    ),
  ),
)
watch(
  () => route.fullPath,
  (path) => loadPage(path),
  { immediate: true },
)
</script>
<template>
  <div
    v-if="page.loading"
    class="navigation-progress"
    role="status"
    aria-label="Đang tải trang"
  >
    <span />
  </div>
  <AppLayout v-if="!component && page.loading">
    <p class="p-8 text-center">Đang tải…</p>
  </AppLayout>
  <div v-else-if="page.error" class="p-8 text-center" role="alert">
    {{ page.error }}
    <button class="underline" @click="loadPage(route.fullPath)">Thử lại</button>
  </div>
  <component
    :is="component"
    v-if="component"
    :key="`${page.url.split('?')[0]}:${page.revision}`"
    v-bind="screenProps"
  />
  <p v-else-if="!page.loading && !page.error" class="p-8">
    Không tìm thấy trang.
  </p>
</template>

<style scoped>
.navigation-progress {
  position: fixed;
  inset: 0 0 auto;
  height: 3px;
  z-index: 10000;
  overflow: hidden;
  pointer-events: none;
}
.navigation-progress span {
  display: block;
  width: 35%;
  height: 100%;
  background: var(--color-primary-500, #6366f1);
  animation: navigation-progress 1.2s ease-in-out infinite;
}
@keyframes navigation-progress {
  from {
    transform: translateX(-100%);
  }
  to {
    transform: translateX(300%);
  }
}
@media (prefers-reduced-motion: reduce) {
  .navigation-progress span {
    width: 100%;
    animation: none;
  }
}
</style>
