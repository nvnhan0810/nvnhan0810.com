<script setup lang="ts">
import ThemeToggle from '../shared/ThemeToggle.vue'
import { watch } from 'vue'
import { Globe, Search, UserRound, LogOut } from 'lucide-vue-next'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { session } from './services'
import { authError, authReady, logout } from './auth'
const route = useRoute()
const router = useRouter()
async function signOut() {
  await logout()
  await router.replace('/login')
}
watch(
  () => session.state.user,
  (user) => {
    if (authReady.value && !user) void router.replace('/login')
  },
)
</script>
<template>
  <p v-if="!authReady" class="session-loading" role="status">
    Checking session…
  </p>
  <RouterView v-else-if="route.name === 'login'" />
  <div v-else-if="session.state.user" class="user-shell">
    <header class="user-header">
      <RouterLink to="/lookup" aria-label="FLC home"
        ><Globe :size="24"
      /></RouterLink>
      <h1>{{ route.name === 'profile' ? 'Profile' : 'Lookup' }}</h1>
      <div class="header-actions">
        <ThemeToggle />
        <button class="header-logout" aria-label="Sign out" @click="signOut">
          <LogOut :size="22" />
        </button>
      </div>
    </header>
    <main class="user-main">
      <p v-if="authError" class="alert" role="alert">{{ authError }}</p>
      <RouterView />
    </main>
    <nav class="user-nav" aria-label="Main navigation">
      <RouterLink
        to="/lookup"
        class="desktop-brand"
        aria-label="Foreign Language Companion"
        ><span class="icon">🌐</span
        ><span>FLC<small>Foreign Language Companion</small></span></RouterLink
      >
      <RouterLink to="/lookup" :class="{ active: route.name === 'lookup' }"
        ><Search class="icon" :size="22" />Lookup</RouterLink
      ><RouterLink to="/profile" :class="{ active: route.name === 'profile' }"
        ><UserRound class="icon" :size="22" />Profile</RouterLink
      >
    </nav>
  </div>
</template>
