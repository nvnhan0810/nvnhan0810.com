<template>
  <div
    class="page-loading"
    role="status"
    aria-label="Đang xử lý"
    aria-live="polite"
  >
    <span />
  </div>
</template>

<style scoped>
.page-loading {
  position: fixed;
  inset: 0 0 auto;
  height: 3px;
  z-index: 10000;
  overflow: hidden;
  pointer-events: none;
}
.page-loading span {
  display: block;
  width: 35%;
  height: 100%;
  background: var(--primary, #4361ee);
  animation: page-loading-progress 1.2s ease-in-out infinite;
}
@keyframes page-loading-progress {
  from {
    transform: translateX(-100%);
  }
  to {
    transform: translateX(300%);
  }
}
@media (prefers-reduced-motion: reduce) {
  .page-loading span {
    width: 100%;
    animation: none;
  }
}
</style>
