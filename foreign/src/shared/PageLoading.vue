<script setup lang="ts">
import { LoaderCircle } from 'lucide-vue-next'
</script>

<template>
  <div
    class="page-loading"
    role="status"
    aria-label="Đang xử lý"
    aria-live="polite"
  >
    <LoaderCircle
      class="page-loading-icon"
      :size="40"
      :stroke-width="2"
      aria-hidden="true"
    />
  </div>
</template>

<style scoped>
.page-loading {
  position: fixed;
  inset: 0;
  z-index: 10000;
  display: grid;
  place-items: center;
  background: color-mix(in srgb, var(--bg, #f8f9fa) 88%, transparent);
  cursor: progress;
}
.page-loading-icon {
  color: var(--primary, #4361ee);
  animation: page-loading-spin 0.8s linear infinite;
}
@keyframes page-loading-spin {
  to {
    transform: rotate(360deg);
  }
}
@media (prefers-reduced-motion: reduce) {
  .page-loading-icon {
    animation: none;
  }
}
</style>
