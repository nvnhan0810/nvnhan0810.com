<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { session } from './services'
import { errorMessage } from '../modules/foreign/infrastructure/transport'
const ready = ref(false)
const error = ref('')
const route = useRoute()
const router = useRouter()
async function login() {
  error.value = ''
  try {
    await session.login()
  } catch (e) {
    error.value = errorMessage(e)
  }
}
async function logout() {
  error.value = ''
  try {
    await session.logout()
  } catch {
    error.value =
      'Đã thoát trên trang này nhưng chưa thu hồi được phiên Identity. Kiểm tra mạng và thử đăng xuất lại.'
  }
  await router.replace('/')
}
watch(
  () => session.state.user,
  (user) => {
    if (ready.value && !user && route.name === 'profile')
      void router.replace('/')
  },
)
router.beforeEach((to) => {
  if (ready.value && to.name === 'profile' && !session.state.user) return '/'
})
onMounted(async () => {
  try {
    if (window.location.pathname === '/auth/callback') {
      const callback = new URL(window.location.href)
      // Remove credentials even when the transaction fails validation.
      window.history.replaceState({}, '', '/auth/callback')
      await session.completeLogin(callback)
      await router.replace('/profile')
    } else {
      await session.restore()
    }
  } catch (e) {
    error.value = errorMessage(e)
    if (window.location.pathname === '/auth/callback') await router.replace('/')
  } finally {
    ready.value = true
    if (route.name === 'profile' && !session.state.user)
      await router.replace('/')
  }
})
</script>

<template>
  <nav class="auth-bar" aria-label="Tài khoản">
    <RouterLink to="/" class="nav-brand">F<span>·</span> Foreign</RouterLink>
    <RouterLink to="/lookup">Tra từ</RouterLink>
    <template v-if="ready">
      <template v-if="session.state.user">
        <RouterLink to="/profile">{{
          session.state.user.name || 'Hồ sơ'
        }}</RouterLink>
        <button :disabled="session.state.busy" @click="logout">
          Đăng xuất
        </button>
      </template>
      <button v-else :disabled="session.state.busy" @click="login">
        Đăng nhập với Identity
      </button>
    </template>
  </nav>
  <p v-if="error" class="auth-error" role="alert">{{ error }}</p>
  <p v-if="!ready" role="status" class="auth-status">
    Đang kiểm tra phiên đăng nhập…
  </p>
  <RouterView v-else />
</template>
