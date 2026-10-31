<script setup lang="ts">
import ThemeToggle from '../../../../shared/ThemeToggle.vue'
import { session } from '../../../../app/services'
import { authError, login } from '../../../../app/auth'
</script>
<template>
  <main class="user-login-page">
    <section class="user-login-card">
      <div class="login-theme"><ThemeToggle /></div>
      <div class="user-login-icon">🌐</div>
      <h1>FLC</h1>
      <p class="subtitle">Foreign Language Companion</p>
      <p v-if="authError" class="alert" role="alert">{{ authError }}</p>
      <button
        class="btn btn-block"
        :disabled="session.state.busy"
        @click="login"
      >
        Sign in with nvnhan0810.com
      </button>
    </section>
  </main>
</template>
