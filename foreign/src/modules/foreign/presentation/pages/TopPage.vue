<script setup lang="ts">
const apiBaseUrl = (
  import.meta.env.VITE_API_BASE_URL || 'http://localhost:8088'
).replace(/\/+$/, '')
</script>

<template>
  <main class="top-page">
    <a class="brand" href="/" aria-label="Foreign — Trang chủ"
      >F<span>·</span></a
    >
    <section class="intro" aria-labelledby="page-title">
      <p class="eyebrow">FOREIGN LANGUAGE COMPANION</p>
      <h1 id="page-title">Một không gian.<br /><span>Nhiều cách học.</span></h1>
      <p class="description">
        Foreign đang được chuẩn bị cho hành trình học ngoại ngữ mới.
      </p>
      <a
        class="api-link"
        :href="`${apiBaseUrl}/foreign/`"
        target="_blank"
        rel="noopener noreferrer"
      >
        Xem API demo <span aria-hidden="true">↗</span>
      </a>
    </section>
    <footer>nvnhan0810.com <span>Foreign</span></footer>
  </main>
</template>
