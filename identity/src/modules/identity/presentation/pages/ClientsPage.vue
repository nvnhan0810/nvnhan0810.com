<script setup lang="ts">
import {
    Blocks,
    Check,
    ChevronRight,
    Copy,
    KeyRound,
    LoaderCircle,
    MoreHorizontal,
    Pencil,
    Plus,
    RefreshCw,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    ToggleLeft,
    ToggleRight,
} from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import { api } from '../../../../app/services.ts'
import { confirmAction, copyText, notify } from '../../../../shared/notifications.ts'
import type { ClientInput, IdentityClient } from '../../domain/models.ts'
import { errorMessage } from '../../infrastructure/transport.ts'
import ClientForm from '../components/ClientForm.vue'
import Modal from '../components/Modal.vue'
const clients = ref<IdentityClient[]>([])
const loading = ref(false)
const error = ref('')
const search = ref('')
const status = ref('all')
const cursor = ref<string | null>(null)
const hasMore = ref(false)
const editorOpen = ref(false)
const selected = ref<IdentityClient | null>(null)
const saving = ref(false)
const formError = ref('')
const actionId = ref('')
const secret = ref('')
const secretClient = ref('')
const filtered = computed(() =>
  clients.value.filter(
    (client) =>
      (status.value === 'all' || client.enabled === (status.value === 'enabled')) &&
      [client.name, client.client_id, client.domain || '', ...client.scopes]
        .join(' ')
        .toLowerCase()
        .includes(search.value.toLowerCase()),
  ),
)
async function load(append = false) {
  if (loading.value) return
  loading.value = true
  error.value = ''
  try {
    const result = await api.clients(append ? cursor.value || undefined : undefined)
    clients.value = append
      ? [
          ...clients.value,
          ...result.clients.filter((client) => !clients.value.some((old) => old.id === client.id)),
        ]
      : result.clients
    cursor.value = result.next_cursor
    hasMore.value = result.clients.length === 25 && !!result.next_cursor
  } catch (cause) {
    error.value = errorMessage(cause)
  } finally {
    loading.value = false
  }
}
function replace(client: IdentityClient) {
  const index = clients.value.findIndex((item) => item.id === client.id)
  if (index >= 0) clients.value[index] = client
  else clients.value.unshift(client)
}
function edit(client: IdentityClient | null) {
  selected.value = client
  formError.value = ''
  editorOpen.value = true
}
async function save(input: ClientInput) {
  saving.value = true
  formError.value = ''
  try {
    const result = selected.value
      ? await api.updateClient(selected.value.id, {
          version: selected.value.version,
          name: input.name,
          domain: input.domain,
          redirect_uris: input.redirect_uris,
          scopes: input.scopes,
          enabled: input.enabled,
        })
      : await api.createClient(input)
    replace(result.client)
    editorOpen.value = false
    notify(selected.value ? 'Đã cập nhật client.' : 'Đã tạo client mới.')
    if (result.client_secret) {
      secret.value = result.client_secret
      secretClient.value = result.client.client_id
    }
  } catch (cause) {
    formError.value = errorMessage(cause)
  } finally {
    saving.value = false
  }
}
async function toggle(client: IdentityClient) {
  if (
    !(await confirmAction({
      title: client.enabled ? 'Tắt kết nối ứng dụng?' : 'Bật lại client?',
      message: client.enabled
        ? `“${client.name}” sẽ không thể bắt đầu đăng nhập, đổi code hoặc refresh token. Access token đã cấp có thể dùng tới khi hết hạn.`
        : `Cho phép “${client.name}” đăng nhập và nhận token trở lại.`,
      label: client.enabled ? 'Tắt client' : 'Bật client',
      danger: client.enabled,
    }))
  )
    return
  actionId.value = client.id
  try {
    const result = await api.updateClient(client.id, {
      version: client.version,
      name: client.name,
      domain: client.domain,
      redirect_uris: client.redirect_uris,
      scopes: client.scopes,
      enabled: !client.enabled,
    })
    replace(result.client)
    notify('Đã cập nhật trạng thái client.')
  } catch (cause) {
    notify(errorMessage(cause), 'error')
  } finally {
    actionId.value = ''
  }
}
async function rotate(client: IdentityClient) {
  if (
    !(await confirmAction({
      title: 'Tạo client secret mới?',
      message: `Secret cũ của “${client.name}” sẽ mất hiệu lực ngay. Hãy cập nhật secret mới ở backend ứng dụng sau thao tác này.`,
      label: 'Rotate secret',
      danger: true,
    }))
  )
    return
  actionId.value = client.id
  try {
    const result = await api.rotateSecret(client.id, client.version)
    replace(result.client)
    if (result.client_secret) {
      secret.value = result.client_secret
      secretClient.value = client.client_id
    }
  } catch (cause) {
    notify(errorMessage(cause), 'error')
  } finally {
    actionId.value = ''
  }
}
function clearSecret() {
  secret.value = ''
  secretClient.value = ''
}
onMounted(() => load())
</script>
<template>
  <div class="page-heading">
    <div>
      <span class="eyebrow muted">WORKSPACE / CLIENTS</span>
      <h1>Ứng dụng & clients<span class="heading-dot">.</span></h1>
      <p>Đăng ký ứng dụng, cấu hình callback và kiểm soát kết nối SSO.</p>
    </div>
    <button class="button primary" @click="edit(null)"><Plus :size="17" />Thêm client</button>
  </div>
  <div class="info-strip">
    <ShieldCheck :size="18" />
    <p>
      Mỗi client là một kết nối riêng.
      <span>Callbacks và scopes được kiểm tra trước khi cấp token.</span>
    </p>
    <span class="strip-label">OAUTH2 + PKCE</span>
  </div>
  <section class="panel">
    <div class="list-toolbar">
      <div class="search-field">
        <Search :size="17" /><input
          v-model="search"
          aria-label="Tìm client trong danh sách đã tải"
          placeholder="Tìm tên, client ID hoặc scope…"
        />
      </div>
      <div class="toolbar-right">
        <div class="select-field">
          <SlidersHorizontal :size="15" /><select
            v-model="status"
            aria-label="Lọc trạng thái client"
          >
            <option value="all">Tất cả trạng thái</option>
            <option value="enabled">Đang bật</option>
            <option value="disabled">Đã tắt</option>
          </select>
        </div>
        <button
          class="icon-button bordered"
          :disabled="loading"
          aria-label="Tải lại clients"
          @click="load()"
        >
          <RefreshCw :size="17" :class="{ spin: loading }" />
        </button>
      </div>
    </div>
    <div v-if="error" class="alert error inset" role="alert">
      {{ error }}<button class="text-button" @click="load()">Thử lại</button>
    </div>
    <div v-if="loading && !clients.length" class="table-loading">
      <span v-for="index in 5" :key="index" class="skeleton skeleton-row" />
    </div>
    <div v-else-if="!filtered.length" class="empty-state">
      <span class="empty-icon"><Blocks :size="30" /></span>
      <h3>{{ clients.length ? 'Không tìm thấy client phù hợp' : 'Kết nối ứng dụng đầu tiên' }}</h3>
      <p>
        {{
          clients.length
            ? 'Thử từ khóa hoặc bộ lọc khác trong danh sách đã tải.'
            : 'Tạo client để ứng dụng sử dụng đăng nhập tập trung qua Identity.'
        }}
      </p>
      <button v-if="!clients.length" class="button primary" @click="edit(null)">
        <Plus :size="16" />Thêm client
      </button>
    </div>
    <div v-else class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>ỨNG DỤNG</th>
            <th>LOẠI CLIENT</th>
            <th>SCOPES</th>
            <th>TRẠNG THÁI</th>
            <th class="align-right">THAO TÁC</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="client in filtered" :key="client.id">
            <td>
              <div class="cell-identity">
                <span class="client-avatar">{{ client.name.slice(0, 1).toUpperCase() }}</span>
                <div>
                  <strong>{{ client.name }}</strong
                  ><span class="copy-line"
                    ><code>{{ client.client_id }}</code
                    ><button
                      class="icon-button tiny"
                      :aria-label="`Sao chép ID ${client.client_id}`"
                      @click="copyText(client.client_id)"
                    >
                      <Copy :size="12" /></button
                  ></span>
                </div>
              </div>
            </td>
            <td>
              <span class="type-badge"
                ><KeyRound v-if="client.client_type === 'confidential'" :size="13" /><Blocks
                  v-else
                  :size="13"
                />{{ client.client_type === 'public' ? 'Public' : 'Confidential' }}</span
              >
            </td>
            <td>
              <div class="scope-list">
                <code v-for="scope in client.scopes.slice(0, 2)" :key="scope">{{ scope }}</code
                ><span v-if="client.scopes.length > 2" :title="client.scopes.join(', ')"
                  >+{{ client.scopes.length - 2 }}</span
                >
              </div>
            </td>
            <td>
              <span :class="['status', client.enabled ? 'enabled' : 'disabled']"
                ><i />{{ client.enabled ? 'Đang bật' : 'Đã tắt' }}</span
              >
            </td>
            <td>
              <div class="row-actions">
                <button
                  class="icon-button"
                  :disabled="!!actionId"
                  :aria-label="`Sửa ${client.name}`"
                  title="Chỉnh sửa"
                  @click="edit(client)"
                >
                  <Pencil :size="16" /></button
                ><button
                  v-if="client.client_type === 'confidential'"
                  class="icon-button"
                  :disabled="!!actionId"
                  :aria-label="`Rotate secret ${client.name}`"
                  title="Rotate secret"
                  @click="rotate(client)"
                >
                  <KeyRound :size="16" /></button
                ><button
                  class="icon-button"
                  :disabled="!!actionId"
                  :aria-label="`${client.enabled ? 'Tắt' : 'Bật'} ${client.name}`"
                  :title="client.enabled ? 'Tắt client' : 'Bật client'"
                  @click="toggle(client)"
                >
                  <LoaderCircle v-if="actionId === client.id" class="spin" :size="18" /><ToggleRight
                    v-else-if="client.enabled"
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
        ><strong>{{ filtered.length }}</strong> hiển thị · {{ clients.length }} client đã tải</span
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
    <MoreHorizontal :size="18" />Tìm kiếm và bộ lọc áp dụng trên các bản ghi đã tải.
  </p>
  <Modal
    :open="editorOpen"
    :title="selected ? 'Chỉnh sửa client' : 'Thêm client mới'"
    wide
    :busy="saving"
    @close="editorOpen = false"
    ><ClientForm
      v-if="editorOpen"
      :client="selected"
      :busy="saving"
      :error="formError"
      @save="save"
      @cancel="editorOpen = false"
  /></Modal>
  <Modal :open="!!secret" title="Lưu client secret của bạn" @close="clearSecret"
    ><div class="form-body">
      <span class="secret-icon"><KeyRound :size="28" /></span>
      <p class="secret-description">
        Secret mới cho <strong>{{ secretClient }}</strong> chỉ hiển thị lần này. Sao chép và lưu vào
        cấu hình backend của ứng dụng.
      </p>
      <label class="field"
        >Client secret<textarea
          :value="secret"
          readonly
          rows="3"
          spellcheck="false"
          aria-label="Client secret mới"
        /></label
      ><button class="button secondary full" @click="copyText(secret)">
        <Copy :size="16" />Sao chép secret
      </button>
      <p class="field-hint">Secret không được lưu trong trình duyệt hoặc hiển thị lại qua API.</p>
    </div>
    <div class="modal-actions">
      <button class="button primary" @click="clearSecret">
        Tôi đã lưu secret<Check :size="16" />
      </button></div
  ></Modal>
</template>
