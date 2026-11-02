<script setup lang="ts">
import { computed, watch, defineAsyncComponent, type Component } from 'vue'
import { useRoute } from 'vue-router'
import { loadPage, page } from '../application/navigation'
const route = useRoute()
const loaders = import.meta.glob<{ default: Component }>('./Pages/**/*.vue')
const modules = Object.fromEntries(
  Object.entries(loaders).map(([name, loader]) => [
    name,
    defineAsyncComponent(loader),
  ]),
)
const component = computed(() => modules[`./Pages/${page.component}.vue`])
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
  <div v-if="page.loading" class="p-8 text-center" role="status">Đang tải…</div>
  <div v-else-if="page.error" class="p-8 text-center" role="alert">
    {{ page.error }}
    <button class="underline" @click="loadPage(route.fullPath)">Thử lại</button>
  </div>
  <component
    :is="component"
    v-else-if="component"
    :key="`${route.path}:${page.revision}`"
    v-bind="screenProps"
  />
  <p v-else class="p-8">Không tìm thấy trang.</p>
</template>
