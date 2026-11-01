<script setup lang="ts">
import { watch } from 'vue'
import { RouterView, useRouter } from 'vue-router'
import { session } from './services'
import { authError, authReady } from './auth'
const router = useRouter()
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
  <template v-else
    ><p v-if="authError" class="alert alert-error" role="alert">
      {{ authError }}
    </p>
    <RouterView
  /></template>
</template>
