<script setup lang="ts">
import { session } from '../../../../app/services'
</script>
<template>
  <main v-if="session.state.user" class="profile-page">
    <p class="eyebrow">TÀI KHOẢN FOREIGN</p>
    <h1>{{ session.state.user.name }}</h1>
    <p>{{ session.state.user.email }}</p>
    <p>Bạn đã đăng nhập qua Identity.</p>
  </main>
</template>
