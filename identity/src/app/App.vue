<script setup lang="ts">
import {
    ArrowUpRight,
    Blocks,
    Check,
    ChevronRight,
    CircleHelp,
    Command,
    LayoutDashboard,
    LoaderCircle,
    LogOut,
    Menu,
    ShieldCheck,
    Users,
    X,
} from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { errorMessage } from '../modules/identity/infrastructure/transport.ts'
import Modal from '../modules/identity/presentation/components/Modal.vue'
import { confirmation, notices, notify, settleConfirmation } from '../shared/notifications.ts'
import { session } from './services.ts'
const route = useRoute()
const router = useRouter()
const error = ref('')
const mobileOpen = ref(false)
const signingOut = ref(false)
function dismissNotice(id: number) {
  notices.value = notices.value.filter((notice) => notice.id !== id)
}
const section = computed(() =>
  route.path === '/clients'
    ? 'Ứng dụng & clients'
    : route.path === '/users'
      ? 'Người dùng'
      : 'Tổng quan',
)
const initials = computed(
  () =>
    session.state.user?.name
      .split(' ')
      .filter(Boolean)
      .slice(-2)
      .map((word) => word[0])
      .join('')
      .toUpperCase() || 'N',
)
const entries = [
  { to: '/', name: 'Tổng quan', icon: LayoutDashboard },
  { to: '/clients', name: 'Ứng dụng & clients', icon: Blocks },
  { to: '/users', name: 'Người dùng', icon: Users },
]
async function login() {
  error.value = ''
  try {
    await session.login()
  } catch (cause) {
    error.value = errorMessage(cause)
  }
}
async function logout() {
  signingOut.value = true
  try {
    await session.logout()
  } catch {
    notify('Đã đăng xuất ở trang này. Chưa xác nhận được việc thu hồi token trên server.', 'error')
  } finally {
    signingOut.value = false
    await router.replace('/')
  }
}
onMounted(async () => {
  if (window.location.pathname !== '/auth/callback') return
  const callback = new URL(window.location.href)
  try {
    await session.completeLogin(callback)
    await router.replace('/')
  } catch (cause) {
    error.value = errorMessage(cause)
    window.history.replaceState({}, '', '/auth/callback')
    await router.replace('/')
  }
})
</script>
<template>
  <div v-if="!session.state.user" class="login-layout">
    <div class="login-visual">
      <div class="brand">
        <span class="brand-mark">N<span>•</span></span>
        <div>nvnhan0810<span class="brand-caption">IDENTITY CONSOLE</span></div>
      </div>
      <div class="login-message">
        <span class="eyebrow"><span class="live-dot" /> MỘT HỆ THỐNG. MỘT DANH TÍNH.</span>
        <h1>Mọi kết nối.<br />Trong <em>tầm kiểm soát.</em></h1>
        <p>Không gian quản trị tập trung cho ứng dụng và người dùng trong hệ sinh thái của bạn.</p>
        <div class="login-illustration" aria-hidden="true">
          <div class="orbit o1" />
          <div class="orbit o2" />
          <div class="orbit-node n1"><Blocks /></div>
          <div class="orbit-center"><ShieldCheck :size="38" /></div>
          <div class="orbit-node n2"><Users /></div>
          <div class="orbit-label">IDENTITY / CONNECTED</div>
        </div>
      </div>
      <div class="login-foot">nvnhan0810.com <span>Identity & access management</span></div>
    </div>
    <main class="login-main">
      <div class="login-card">
        <span class="small-icon"><ShieldCheck :size="26" /></span
        ><span class="eyebrow muted">ADMIN WORKSPACE</span>
        <h2>Chào mừng trở lại.</h2>
        <p>Đăng nhập bằng tài khoản Google quản trị để tiếp tục.</p>
        <div v-if="error" class="alert error" role="alert">{{ error }}</div>
        <button class="button primary login-button" :disabled="session.state.busy" @click="login">
          <LoaderCircle v-if="session.state.busy" :size="18" class="spin" /><svg
            v-else
            width="19"
            height="19"
            viewBox="0 0 24 24"
            aria-hidden="true"
          >
            <path
              fill="currentColor"
              d="M21.6 12.2c0-.7-.1-1.4-.2-2.2H12v4.2h5.4a4.6 4.6 0 0 1-2 3v2.7h3.4c2-1.9 2.8-4.5 2.8-7.7M12 22c2.7 0 5-1 6.8-2.5l-3.4-2.7c-.9.6-2 1-3.4 1a6 6 0 0 1-5.6-4.2H3v2.8A10 10 0 0 0 12 22M6.4 13.6a6 6 0 0 1 0-3.2V7.6H3a10 10 0 0 0 0 8.8zM12 6.2c1.5 0 2.8.5 3.8 1.5l3-3A10 10 0 0 0 3 7.6l3.4 2.8A6 6 0 0 1 12 6.2"
            /></svg
          >{{ session.state.busy ? 'Đang xác thực…' : 'Tiếp tục với Google'
          }}<ArrowUpRight :size="17" />
        </button>
        <div class="login-note">
          <ShieldCheck :size="16" /><span
            >Chỉ dành cho tài khoản system root.<br />Quyền truy cập được xác minh bởi
            Identity.</span
          >
        </div>
      </div>
      <span class="login-footer"
        >PRIVATE ACCESS <span>© {{ new Date().getFullYear() }} nvnhan0810</span></span
      >
    </main>
  </div>
  <div v-else class="workspace">
    <button
      v-if="mobileOpen"
      class="sidebar-overlay"
      aria-label="Đóng menu"
      @click="mobileOpen = false"
    />
    <aside :class="['sidebar', { 'sidebar-open': mobileOpen }]">
      <RouterLink to="/" class="brand" @click="mobileOpen = false"
        ><span class="brand-mark">N<span>•</span></span>
        <div>nvnhan0810<span class="brand-caption">IDENTITY CONSOLE</span></div></RouterLink
      ><span class="nav-label">WORKSPACE</span>
      <nav aria-label="Điều hướng chính">
        <RouterLink
          v-for="entry in entries"
          :key="entry.to"
          :to="entry.to"
          :class="['nav-link', { active: route.path === entry.to }]"
          @click="mobileOpen = false"
          ><component :is="entry.icon" :size="19" />{{ entry.name
          }}<ChevronRight v-if="route.path === entry.to" class="nav-arrow" :size="15"
        /></RouterLink>
      </nav>
      <div class="sidebar-bottom">
        <div class="access-card">
          <ShieldCheck :size="20" /><strong>System root</strong
          ><span>Toàn quyền quản trị Identity</span>
        </div>
        <a
          class="sidebar-help"
          href="https://nvnhan0810.com"
          target="_blank"
          rel="noopener noreferrer"
          ><CircleHelp :size="16" />nvnhan0810.com<ArrowUpRight :size="14"
        /></a>
        <div class="sidebar-version"><span class="live-dot" />IDENTITY ADMIN <span>01</span></div>
      </div>
    </aside>
    <div class="workspace-body">
      <header class="topbar">
        <div class="breadcrumb">
          <button
            class="icon-button mobile-menu"
            aria-label="Mở menu"
            @click="mobileOpen = !mobileOpen"
          >
            <Menu :size="21" /></button
          ><Command :size="16" /><span>Workspace</span><ChevronRight :size="14" /><strong>{{
            section
          }}</strong>
        </div>
        <div class="topbar-account">
          <span class="root-badge"><ShieldCheck :size="13" />Root access</span
          ><span class="avatar">{{ initials }}</span>
          <div class="account-text">
            <strong>{{ session.state.user.name }}</strong
            ><span>System administrator</span>
          </div>
          <button
            class="icon-button"
            title="Đăng xuất trang admin"
            aria-label="Đăng xuất trang admin"
            :disabled="signingOut"
            @click="logout"
          >
            <LogOut :size="17" />
          </button>
        </div>
      </header>
      <main class="main-content"><RouterView /></main>
      <footer class="workspace-footer">
        <span>IDENTITY CONSOLE <i />nvnhan0810 ecosystem</span
        ><span><ShieldCheck :size="13" />Quyền truy cập được bảo vệ</span>
      </footer>
    </div>
  </div>
  <div class="toast-stack" aria-live="polite">
    <div v-for="notice in notices" :key="notice.id" :class="['toast', notice.kind]">
      <Check v-if="notice.kind === 'success'" :size="18" /><X v-else :size="18" /><span>{{
        notice.message
      }}</span
      ><button class="icon-button" aria-label="Đóng thông báo" @click="dismissNotice(notice.id)">
        <X :size="15" />
      </button>
    </div>
  </div>
  <Modal
    :open="!!confirmation"
    :title="confirmation?.title || 'Xác nhận'"
    @close="settleConfirmation(false)"
    ><p class="confirm-message">{{ confirmation?.message }}</p>
    <div class="modal-actions">
      <button class="button secondary" @click="settleConfirmation(false)">Hủy</button
      ><button
        :class="['button', confirmation?.danger ? 'danger' : 'primary']"
        @click="settleConfirmation(true)"
      >
        {{ confirmation?.label }}
      </button>
    </div></Modal
  >
</template>
