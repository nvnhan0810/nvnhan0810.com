<script setup lang="ts">
import { computed, markRaw, shallowReactive, watch, type Component } from "vue";
import { useRoute } from "vue-router";
import { page, loadPage } from "../application/navigation";
const route = useRoute();
const loaders = import.meta.glob<{ default: Component }>("./pages/**/*.vue");
const components = shallowReactive<Record<string, Component>>({});
async function prepare(name: string) {
  if (components[name]) return;
  const loader = loaders[`./pages/${name}.vue`];
  if (loader) components[name] = markRaw((await loader()).default);
}
const component = computed(() =>
  page.payload ? components[page.payload.component] : null,
);
const props = computed(() =>
  Object.fromEntries(
    Object.entries(page.payload?.props ?? {}).filter(
      ([key]) => !["auth", "flash", "errors"].includes(key),
    ),
  ),
);
watch(
  () => route.fullPath,
  (path) => loadPage(path, prepare),
  { immediate: true },
);
watch(
  () => page.payload?.component,
  async (name) => {
    if (name) await prepare(name);
  },
);
</script>
<template>
  <div v-if="page.error" class="error" role="alert">
    {{ page.error }}
    <button @click="loadPage(route.fullPath, prepare)">Thử lại</button>
  </div>
  <component
    :is="component"
    v-if="component"
    :key="`${page.payload?.component}:${page.url.split('?')[0]}`"
    v-bind="props"
  />
  <div v-else-if="page.loading" class="empty">Đang tải công việc…</div>
  <div v-else class="empty">Không tìm thấy trang.</div>
</template>
