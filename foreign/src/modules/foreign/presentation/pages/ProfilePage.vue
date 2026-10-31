<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { LogOut } from 'lucide-vue-next'
import { session } from '../../../../app/services'
import { logout } from '../../../../app/auth'
import ThemeToggle from '../../../../shared/ThemeToggle.vue'
import { theme } from '../../../../shared/theme'
const router = useRouter()
const signingOut = ref(false)
async function signOut() {
  signingOut.value = true
  try {
    await logout()
    await router.replace('/login')
  } finally {
    signingOut.value = false
  }
}
</script>
<template>
  <div v-if="session.state.user" class="profile-page">
    <section class="card profile-account">
      <h2 class="card-title">{{ session.state.user.name }}</h2>
      <p class="muted">{{ session.state.user.email }}</p>
      <p>Bạn đã đăng nhập qua Identity.</p>
    </section>
    <section class="card" aria-labelledby="profile-settings-title">
      <h2 id="profile-settings-title" class="card-title">Cài đặt</h2>
      <div class="profile-setting-row">
        <div>
          <h3>Giao diện</h3>
          <p class="muted">
            {{
              theme === 'dark'
                ? 'Đang dùng giao diện tối'
                : 'Đang dùng giao diện sáng'
            }}
          </p>
        </div>
        <ThemeToggle />
      </div>
      <div class="profile-setting-row">
        <div>
          <h3>Phiên đăng nhập</h3>
          <p class="muted">Đăng xuất tài khoản khỏi Foreign.</p>
        </div>
        <button
          type="button"
          class="btn btn-secondary profile-logout"
          :disabled="signingOut"
          @click="signOut"
        >
          <LogOut :size="18" aria-hidden="true" />{{
            signingOut ? 'Đang đăng xuất…' : 'Đăng xuất'
          }}
        </button>
      </div>
    </section>
  </div>
</template>
