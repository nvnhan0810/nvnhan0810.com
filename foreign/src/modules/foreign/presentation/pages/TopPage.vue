<script setup lang="ts">
const apiBaseUrl = (
  import.meta.env.VITE_API_BASE_URL || 'http://localhost:8088'
).replace(/\/+$/, '')
</script>

<template>
  <main class="top-page">
    <a class="brand" href="/" aria-label="Foreign — Home">F<span>·</span></a>
    <section class="intro" aria-labelledby="page-title">
      <p class="eyebrow">FOREIGN LANGUAGE COMPANION</p>
      <h1 id="page-title">One space.<br /><span>Many ways to learn.</span></h1>
      <p class="description">
        Foreign is getting ready for a new language learning journey.
      </p>
      <a
        class="api-link"
        :href="`${apiBaseUrl}/foreign/`"
        target="_blank"
        rel="noopener noreferrer"
      >
        View API demo <span aria-hidden="true">↗</span>
      </a>
    </section>
    <footer>nvnhan0810.com <span>Foreign</span></footer>
  </main>
</template>
