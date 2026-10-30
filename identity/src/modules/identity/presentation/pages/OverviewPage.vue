<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import {
  ArrowRight,
  ArrowUpRight,
  Blocks,
  CheckCircle2,
  Fingerprint,
  KeyRound,
  RefreshCw,
  ShieldCheck,
  Users,
} from 'lucide-vue-next'
import { api, session } from '../../../../app/services'
import type { IdentityClient, IdentityUser } from '../../domain/models'
import { errorMessage } from '../../infrastructure/transport'
const clients = ref<IdentityClient[]>([])
const users = ref<IdentityUser[]>([])
const loading = ref(true)
const error = ref('')
const active = computed(() => clients.value.filter((client) => client.enabled).length)
const stats = computed(() => [
  {
    title: 'Ứng dụng & clients',
    value: clients.value.length,
    icon: Blocks,
    note: 'Kết nối trong danh sách',
    color: 'green',
  },
  {
    title: 'Người dùng',
    value: users.value.length,
    icon: Users,
    note: 'Danh tính trong danh sách',
    color: 'blue',
  },
  {
    title: 'Clients đang bật',
    value: active.value,
    icon: CheckCircle2,
    note: 'Sẵn sàng xác thực SSO',
    color: 'purple',
  },
])
async function load() {
  loading.value = true
  error.value = ''
  const results = await Promise.allSettled([api.clients(), api.users()])
  const [c, u] = results
  if (c.status === 'fulfilled') clients.value = c.value.clients
  if (u.status === 'fulfilled') users.value = u.value.users
  error.value = results
    .filter((result) => result.status === 'rejected')
    .map((result) => errorMessage(result.reason))
    .join(' ')
  loading.value = false
}
onMounted(load)
</script>
<template>
  <div class="page-heading">
    <div>
      <span class="eyebrow muted">WORKSPACE / OVERVIEW</span>
      <h1>Tổng quan hệ thống<span class="heading-dot">.</span></h1>
      <p>Quản lý các kết nối và danh tính trong cùng một không gian.</p>
    </div>
    <button class="button secondary" :disabled="loading" @click="load">
      <RefreshCw :size="16" :class="{ spin: loading }" />Làm mới
    </button>
  </div>
  <div v-if="error" class="alert error" role="alert">
    {{ error }} <button class="text-button" @click="load">Thử lại</button>
  </div>
  <section class="welcome-banner">
    <div>
      <span class="eyebrow"><span class="live-dot" /> IDENTITY WORKSPACE</span>
      <h2>Xin chào, {{ session.state.user?.name.split(' ').at(-1) }}.</h2>
      <p>Các ứng dụng và người dùng của bạn.<br />Một điểm quản trị, mọi kết nối được kiểm soát.</p>
      <RouterLink to="/clients" class="button primary"
        >Quản lý clients<ArrowUpRight :size="16"
      /></RouterLink>
    </div>
    <div class="banner-graphic" aria-hidden="true">
      <div class="graphic-ring r1" />
      <div class="graphic-ring r2" />
      <span class="graphic-core"><Fingerprint :size="66" stroke-width="1.2" /></span
      ><span class="graphic-chip chip-top"><KeyRound :size="15" />OAuth2</span
      ><span class="graphic-chip chip-bottom"><ShieldCheck :size="15" />Single sign-on</span>
    </div>
  </section>
  <div class="section-label">
    <h2>Danh sách hiện tại</h2>
    <span>Số liệu từ tối đa 25 bản ghi đầu tiên mỗi danh sách</span>
  </div>
  <section class="stats-grid">
    <article v-for="stat in stats" :key="stat.title" class="stat-card">
      <div class="stat-top">
        <span>{{ stat.title }}</span
        ><span :class="['stat-icon', stat.color]"><component :is="stat.icon" :size="20" /></span>
      </div>
      <div :class="['stat-value', { skeleton: loading }]">
        {{ loading ? '—' : stat.value.toString().padStart(2, '0') }}
      </div>
      <div class="stat-note"><span class="mini-dot" />{{ stat.note }}</div>
    </article>
  </section>
  <div class="overview-grid">
    <section class="panel">
      <div class="panel-heading">
        <div>
          <h2>Kết nối ứng dụng</h2>
          <p>Clients đã đăng ký với Identity.</p>
        </div>
        <RouterLink to="/clients" class="text-button"
          >Xem tất cả<ArrowRight :size="15"
        /></RouterLink>
      </div>
      <div v-if="loading" class="table-loading">
        <span class="skeleton skeleton-row" /><span class="skeleton skeleton-row" /><span
          class="skeleton skeleton-row"
        />
      </div>
      <div v-else-if="!clients.length" class="empty-state compact">
        <Blocks :size="30" />
        <h3>Chưa có client nào</h3>
        <p>Đăng ký ứng dụng đầu tiên để kết nối SSO.</p>
        <RouterLink to="/clients" class="text-button"
          >Đến trang clients<ArrowRight :size="15"
        /></RouterLink>
      </div>
      <RouterLink
        v-for="client in clients.slice(0, 5)"
        v-else
        :key="client.id"
        to="/clients"
        class="connection-row"
        ><span class="client-avatar">{{ client.name.slice(0, 1).toUpperCase() }}</span>
        <div>
          <strong>{{ client.name }}</strong
          ><code>{{ client.client_id }}</code>
        </div>
        <span :class="['status', client.enabled ? 'enabled' : 'disabled']"
          ><i />{{ client.enabled ? 'Đang bật' : 'Đã tắt' }}</span
        ><ArrowUpRight :size="15"
      /></RouterLink>
    </section>
    <section class="panel identity-panel">
      <div class="panel-heading">
        <div>
          <h2>Quyền quản trị</h2>
          <p>Danh tính đang sử dụng workspace.</p>
        </div>
        <ShieldCheck :size="21" />
      </div>
      <div class="root-identity">
        <span class="root-symbol"><ShieldCheck :size="29" /></span
        ><span class="status enabled">SYSTEM ROOT</span>
        <h3>{{ session.state.user?.name }}</h3>
        <p>{{ session.state.user?.email }}</p>
      </div>
      <div class="permission-list">
        <span><CheckCircle2 :size="16" />Quản lý ứng dụng & callback</span
        ><span><CheckCircle2 :size="16" />Quản lý trạng thái người dùng</span
        ><span><CheckCircle2 :size="16" />Thu hồi phiên & rotate secret</span>
      </div>
      <RouterLink to="/users" class="button secondary full"
        >Quản lý người dùng<ArrowRight :size="16"
      /></RouterLink>
    </section>
  </div>
</template>
