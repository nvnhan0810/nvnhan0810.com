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
  <main
    class="min-h-[100dvh] flex items-center justify-center px-safe py-10 bg-gradient-to-b from-primary-50 to-app dark:from-slate-900 dark:to-app"
  >
    <div class="w-full max-w-sm">
      <div class="flex flex-col items-center text-center">
        <img
          src="/icons/icon-192.png"
          alt=""
          class="w-20 h-20 rounded-3xl shadow-xl shadow-primary-600/25"
        />
        <h1 class="mt-5 text-3xl font-bold tracking-tight text-content">
          MyWallet
        </h1>
        <p class="mt-2 text-content-muted">
          Quản lý ví, thu chi và khoản vay của bạn.
        </p>
      </div>
      <div class="card mt-8 p-6">
        <p
          v-if="error"
          role="alert"
          class="mb-4 rounded-xl bg-red-50 dark:bg-red-900/30 px-3 py-2 text-sm text-red-700 dark:text-red-300"
        >
          {{ error }}
        </p>
        <button
          class="btn btn-primary w-full min-h-12 text-base"
          :disabled="session.state.busy"
          @click="login"
        >
          {{
            session.state.busy ? 'Đang đăng nhập…' : 'Đăng nhập qua Identity'
          }}
        </button>
        <p class="mt-4 text-center text-xs text-content-muted">
          Đăng nhập một lần cho mọi ứng dụng của bạn.
        </p>
      </div>
    </div>
  </main>
</template>
