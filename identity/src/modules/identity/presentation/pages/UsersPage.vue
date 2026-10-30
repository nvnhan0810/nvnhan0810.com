<script setup lang="ts">
import {
    Check,
    ChevronRight,
    Copy,
    Eye,
    LoaderCircle,
    RefreshCw,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    ToggleLeft,
    ToggleRight,
    UserRound,
    Users,
    XCircle,
} from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import { api } from '../../../../app/services.ts'
import { confirmAction, copyText, notify } from '../../../../shared/notifications.ts'
import type { IdentityUser } from '../../domain/models.ts'
import { errorMessage } from '../../infrastructure/transport.ts'
import Modal from '../components/Modal.vue'
const users = ref<IdentityUser[]>([])
const loading = ref(false)
const error = ref('')
const search = ref('')
const status = ref('all')
const cursor = ref<string | null>(null)
const hasMore = ref(false)
const actionId = ref('')
const selected = ref<IdentityUser | null>(null)
const filtered = computed(() =>
  users.value.filter(
    (user) =>
      (status.value === 'all' || user.enabled === (status.value === 'enabled')) &&
      `${user.name} ${user.email}`.toLowerCase().includes(search.value.toLowerCase()),
  ),
)
async function load(append = false) {
  if (loading.value) return
  loading.value = true
  error.value = ''
  try {
    const result = await api.users(append ? cursor.value || undefined : undefined)
    users.value = append
      ? [
          ...users.value,
          ...result.users.filter((user) => !users.value.some((old) => old.id === user.id)),
        ]
      : result.users
    cursor.value = result.next_cursor
    hasMore.value = result.users.length === 25 && !!result.next_cursor
  } catch (cause) {
    error.value = errorMessage(cause)
  } finally {
    loading.value = false
  }
}
async function toggle(user: IdentityUser) {
  if (user.is_system_root) return
  if (
    !(await confirmAction({
      title: user.enabled ? 'Khóa tài khoản người dùng?' : 'Mở lại tài khoản?',
      message: user.enabled
        ? `${user.email} sẽ không thể đăng nhập, đổi code hoặc refresh token. Các phiên và refresh grants sẽ được thu hồi. Access token đã cấp có thể còn hiệu lực tới khi hết hạn.`
        : `Cho phép ${user.email} đăng nhập lại. Các phiên đã bị thu hồi không được khôi phục.`,
      label: user.enabled ? 'Khóa tài khoản' : 'Mở tài khoản',
      danger: user.enabled,
    }))
  )
    return
  actionId.value = user.id
  try {
    const updated = await api.setUserEnabled(user.id, !user.enabled)
    users.value = users.value.map((item) => (item.id === updated.id ? updated : item))
    notify(updated.enabled ? 'Đã mở lại tài khoản.' : 'Đã khóa tài khoản và thu hồi phiên.')
  } catch (cause) {
    notify(errorMessage(cause), 'error')
  } finally {
    actionId.value = ''
  }
}
async function revoke(user: IdentityUser) {
  if (user.is_system_root) return
  if (
    !(await confirmAction({
      title: 'Thu hồi các phiên đăng nhập?',
      message: `Thu hồi toàn bộ refresh grants và phiên SSO của ${user.email}. Access token tại các API khác có thể còn hiệu lực tới khi hết hạn. Người dùng cần đăng nhập lại.`,
      label: 'Thu hồi phiên',
      danger: true,
    }))
  )
    return
  actionId.value = user.id
  try {
    await api.revokeUser(user.id)
    notify('Đã thu hồi các phiên và refresh grants của người dùng.')
  } catch (cause) {
    notify(errorMessage(cause), 'error')
  } finally {
    actionId.value = ''
  }
}
onMounted(() => load())
</script>
<template>
  <div class="page-heading">
    <div>
      <span class="eyebrow muted">WORKSPACE / USERS</span>
      <h1>Người dùng<span class="heading-dot">.</span></h1>
      <p>Quản lý danh tính Google và trạng thái truy cập của người dùng.</p>
    </div>
    <span class="page-tag"><Users :size="16" />Identity directory</span>
  </div>
  <div class="info-strip">
    <ShieldCheck :size="18" />
    <p>
      Danh tính được tạo khi đăng nhập Google lần đầu. <span>System root luôn được bảo vệ.</span>
    </p>
    <span class="strip-label">GOOGLE SSO</span>
  </div>
  <section class="panel">
    <div class="list-toolbar">
      <div class="search-field">
        <Search :size="17" /><input
          v-model="search"
          aria-label="Tìm người dùng trong danh sách đã tải"
          placeholder="Tìm theo tên hoặc email…"
        />
      </div>
      <div class="toolbar-right">
        <div class="select-field">
          <SlidersHorizontal :size="15" /><select
            v-model="status"
            aria-label="Lọc trạng thái người dùng"
          >
            <option value="all">Tất cả trạng thái</option>
            <option value="enabled">Đang hoạt động</option>
            <option value="disabled">Đã khóa</option>
          </select>
        </div>
        <button
          class="icon-button bordered"
          aria-label="Tải lại người dùng"
          :disabled="loading"
          @click="load()"
        >
          <RefreshCw :size="17" :class="{ spin: loading }" />
        </button>
      </div>
    </div>
    <div v-if="error" class="alert error inset" role="alert">
      {{ error }}<button class="text-button" @click="load()">Thử lại</button>
    </div>
    <div v-if="loading && !users.length" class="table-loading">
      <span v-for="index in 5" :key="index" class="skeleton skeleton-row" />
    </div>
    <div v-else-if="!filtered.length" class="empty-state">
      <span class="empty-icon"><Users :size="30" /></span>
      <h3>{{ users.length ? 'Không tìm thấy người dùng' : 'Chưa có người dùng' }}</h3>
      <p>
        {{
          users.length
            ? 'Thử tên, email hoặc trạng thái khác trong danh sách đã tải.'
            : 'Người dùng xuất hiện ở đây sau khi đăng nhập Google qua Identity.'
        }}
      </p>
    </div>
    <div v-else class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>NGƯỜI DÙNG</th>
            <th>EMAIL</th>
            <th>VAI TRÒ</th>
            <th>TRẠNG THÁI</th>
            <th class="align-right">THAO TÁC</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in filtered" :key="user.id">
            <td>
              <div class="cell-identity">
                <span :class="['user-avatar', { 'root-avatar': user.is_system_root }]">{{
                  user.name
                    .split(' ')
                    .slice(-2)
                    .map((word) => word[0])
                    .join('')
                    .toUpperCase()
                }}</span>
                <div>
                  <strong>{{ user.name }}</strong
                  ><span class="small-id">{{ user.id.slice(0, 8) }}</span>
                </div>
              </div>
            </td>
            <td>
              <span class="email-cell">{{ user.email }}</span>
            </td>
            <td>
              <span v-if="user.is_system_root" class="root-badge"
                ><ShieldCheck :size="13" />System root</span
              ><span v-else class="type-badge"><UserRound :size="13" />User</span>
            </td>
            <td>
              <span :class="['status', user.enabled ? 'enabled' : 'disabled']"
                ><i />{{ user.enabled ? 'Hoạt động' : 'Đã khóa' }}</span
              >
            </td>
            <td>
              <div class="row-actions">
                <button
                  class="icon-button"
                  :aria-label="`Chi tiết ${user.name}`"
                  title="Chi tiết"
                  @click="selected = user"
                >
                  <Eye :size="16" /></button
                ><button
                  class="icon-button"
                  :disabled="!!actionId || user.is_system_root"
                  :aria-label="`Thu hồi phiên của ${user.email}`"
                  :title="user.is_system_root ? 'System root được bảo vệ' : 'Thu hồi phiên'"
                  @click="revoke(user)"
                >
                  <XCircle :size="17" /></button
                ><button
                  class="icon-button"
                  :disabled="!!actionId || user.is_system_root"
                  :aria-label="`${user.enabled ? 'Khóa' : 'Mở'} ${user.email}`"
                  :title="
                    user.is_system_root
                      ? 'Không thể khóa system root'
                      : user.enabled
                        ? 'Khóa tài khoản'
                        : 'Mở tài khoản'
                  "
                  @click="toggle(user)"
                >
                  <LoaderCircle v-if="actionId === user.id" class="spin" :size="18" /><ToggleRight
                    v-else-if="user.enabled"
                    class="green-text"
                    :size="22"
                  /><ToggleLeft v-else :size="22" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="table-footer">
      <span
        ><strong>{{ filtered.length }}</strong> hiển thị · {{ users.length }} người dùng đã
        tải</span
      ><button
        v-if="hasMore"
        class="button secondary small"
        :disabled="loading"
        @click="load(true)"
      >
        {{ loading ? 'Đang tải…' : 'Tải thêm' }}<ChevronRight :size="15" /></button
      ><span v-else-if="!error && !loading" class="subtle"
        ><Check :size="13" />Đã tải hết danh sách</span
      >
    </div>
  </section>
  <p class="page-note">
    <ShieldCheck :size="15" />Quyền quản trị hiện chỉ dành cho system root. Chưa hỗ trợ sửa email
    hoặc phân vai trò khác.
  </p>
  <Modal :open="!!selected" title="Thông tin người dùng" @close="selected = null"
    ><div v-if="selected" class="form-body">
      <div class="user-detail-heading">
        <span class="user-avatar large">{{ selected.name.slice(0, 1) }}</span>
        <h3>{{ selected.name }}</h3>
        <span :class="['status', selected.enabled ? 'enabled' : 'disabled']">{{
          selected.enabled ? 'Đang hoạt động' : 'Đã khóa'
        }}</span>
      </div>
      <dl class="detail-list">
        <div>
          <dt>Email</dt>
          <dd>{{ selected.email }}</dd>
        </div>
        <div>
          <dt>User ID</dt>
          <dd>
            <code>{{ selected.id }}</code
            ><button
              class="icon-button tiny"
              aria-label="Sao chép user ID"
              @click="copyText(selected.id)"
            >
              <Copy :size="13" />
            </button>
          </dd>
        </div>
        <div>
          <dt>Vai trò</dt>
          <dd>{{ selected.is_system_root ? 'System root' : 'User' }}</dd>
        </div>
        <div>
          <dt>Đăng nhập</dt>
          <dd>Google SSO</dd>
        </div>
      </dl>
    </div>
    <div class="modal-actions">
      <button class="button secondary" @click="selected = null">Đóng</button>
    </div></Modal
  >
</template>
