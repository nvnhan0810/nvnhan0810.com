<script setup lang="ts">
import { ref } from 'vue'
import { session } from '../app/services'
import { authError } from '../app/router'
const error = ref(authError.message)
async function login() {
  error.value = ''
  try {
    await session.login()
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Không thể đăng nhập.'
  }
}
</script>
<template>
  <main class="min-h-screen flex items-center justify-center p-4">
    <div
      class="max-w-md w-full bg-surface border border-default rounded-2xl p-8"
    >
      <h1 class="text-2xl font-bold text-primary-600">MyWallet</h1>
      <p class="mt-2 text-content-secondary">
        Quản lý ví, thu chi và khoản vay của bạn.
      </p>
      <p v-if="error" role="alert" class="mt-4 text-red-600">{{ error }}</p>
      <button
        class="mt-6 w-full bg-primary-600 text-white rounded-lg p-3 disabled:opacity-50"
        :disabled="session.state.busy"
        @click="login"
      >
        {{ session.state.busy ? 'Đang đăng nhập…' : 'Đăng nhập qua Identity' }}
      </button>
    </div>
  </main>
</template>
