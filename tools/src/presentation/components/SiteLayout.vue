<template>
  <div class="site-shell">
    <header class="site-header">
      <a :href="homeHref" class="brand" aria-label="Tools — Trang chủ"><span class="brand-icon">n.</span> nvnhan0810 <span class="brand-divider">/</span> <strong>tools</strong></a>
      <nav aria-label="Điều hướng chính"><a :href="homeHref">Tất cả công cụ <span aria-hidden="true">↗</span></a></nav>
    </header>
    <main id="main"><slot /></main>
    <footer><span>nvnhan0810 / tools</span><span>Những công cụ nhỏ. Cho công việc mỗi ngày.</span><a href="https://nvnhan0810.com">Website ↗</a></footer>
  </div>
</template>

<script setup lang="ts">
defineProps<{ homeHref: string }>()
</script>
