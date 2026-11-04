<script setup lang="ts">
import { RoutePath } from './router/paths'

import { watch } from 'vue'
import PageLoading from '../shared/PageLoading.vue'
import { pendingRequests } from '../shared/loading'
import { RouterView, useRouter } from 'vue-router'
import { session } from './services'
import { authError, authReady } from './auth'
const router = useRouter()
watch(
  () => session.state.user,
  (user) => {
    if (authReady.value && !user) void router.replace(RoutePath.login)
  },
)
</script>
<template>
  <PageLoading v-if="!authReady || session.state.busy || pendingRequests > 0" />
  <template v-if="authReady"
    ><p v-if="authError" class="alert alert-error" role="alert">
      {{ authError }}
    </p>
    <RouterView
  /></template>
</template>
