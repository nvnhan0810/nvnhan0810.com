<script setup lang="ts">
import { tools } from '../domain/tools'
import SiteLayout from './components/SiteLayout.vue'
</script>

<template>
  <SiteLayout home-href="./">
    <section class="home-hero">
      <p class="eyebrow"><span class="status-dot"></span> THE TOOLBOX</p>
      <h1>Ít thao tác hơn.<br /><span>Nhiều việc được làm hơn.</span></h1>
      <p class="lead">Những công cụ tôi xây dựng để công việc hằng ngày gọn gàng hơn.<br class="desktop-break" /> Khám phá, cài đặt và bắt đầu theo cách của bạn.</p>
      <a class="text-link" href="#tools">Khám phá công cụ <span aria-hidden="true">↓</span></a>
      <div class="hero-decoration" aria-hidden="true"><span>~/tools</span><strong>&gt;_</strong><small>BUILT FOR YOUR WORKFLOW</small></div>
    </section>
    <section id="tools" class="catalog">
      <div class="section-heading"><h2>Bộ công cụ</h2><span>01 công cụ</span></div>
      <a v-for="tool in tools" :key="tool.slug" :href="`${tool.slug}/`" class="tool-card">
        <div class="card-art" aria-hidden="true"><div class="mini-terminal"><div class="terminal-top"><i></i><i></i><i></i><span>nvn-agent</span></div><div class="terminal-body"><span class="muted">~/your-next-project</span><p><span class="green">❯</span> nvn-agent<span class="cursor"></span></p><div class="terminal-grid"><span>FILES<br /><br />src/<br /> components/<br /> main.ts<br />package.json</span><span><b>Ready when you are.</b><br /><br />Code. Chat. Create.<br /><br /><span class="green">› What shall we build?</span></span></div></div></div></div>
        <div class="card-copy"><p class="eyebrow">{{ tool.category }}</p><h3>{{ tool.name }} <span aria-hidden="true">↗</span></h3><p>{{ tool.description }}</p><div class="tags"><span v-for="platform in tool.platforms" :key="platform">{{ platform }}</span></div><span class="text-link">Giới thiệu & cài đặt <span aria-hidden="true">→</span></span></div>
      </a>
      <p class="catalog-note">Đang bắt đầu từ những điều thiết thực. Các công cụ mới sẽ được bổ sung tại đây.</p>
    </section>
  </SiteLayout>
</template>
