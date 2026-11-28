<script setup lang="ts">
import type { PropType } from 'vue'
import type { Wallet } from '@/domain/entities'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link, router } from '@/application/navigation'
import Sortable from 'sortablejs'
import { action } from '@/infrastructure/wallets-api'
import AppLayout from '@/Layouts/AppLayout.vue'
import NavIcon from '@/Components/NavIcon.vue'
import {
  creditUsagePercent,
  formatMoney,
  summarizeWallets,
  WALLET_TYPES,
} from '@/domain'

const props = defineProps({
  wallets: { type: Array as PropType<Wallet[]>, default: () => [] },
  walletTypes: { type: Object, default: () => WALLET_TYPES },
})

const localWallets = ref<Wallet[]>([])
const summary = computed(() => summarizeWallets(localWallets.value))

/** Avatar colour + glyph per wallet type. */
const TYPE_STYLE: Record<string, { tone: string; glyph: string }> = {
  cash: {
    tone: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    glyph:
      'M3 7h18v10H3z M12 14.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z M6 10v.01M18 14v.01',
  },
  bank: {
    tone: 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
    glyph: 'M3 10 12 4l9 6 M5 10v8m4-8v8m6-8v8m4-8v8 M3 20h18',
  },
  credit_card: {
    tone: 'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300',
    glyph:
      'M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z M3 10h18 M7 15h3',
  },
  e_wallet: {
    tone: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    glyph:
      'M7 3h10a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z M11 18h2',
  },
}
function typeStyle(type: string) {
  return TYPE_STYLE[type] ?? TYPE_STYLE.cash
}
const gridEl = ref<HTMLElement | null>(null)
const sortStatus = ref('')
const sortStatusVisible = ref(false)
let sortable: Sortable | null = null
let saveTimer: ReturnType<typeof setTimeout> | undefined
let saving = false
let statusTimer: ReturnType<typeof setTimeout> | undefined

function syncFromProps() {
  localWallets.value = props.wallets.map((w) => ({
    ...w,
    is_pinned: !!w.is_pinned,
  }))
}

watch(() => props.wallets, syncFromProps, { deep: true })

function setStatus(text: string, fading = true) {
  sortStatus.value = text
  sortStatusVisible.value = true
  clearTimeout(statusTimer)
  if (fading) {
    statusTimer = setTimeout(() => {
      sortStatusVisible.value = false
    }, 1600)
  }
}

function collectPayload() {
  return localWallets.value.map((w, order) => ({
    id: w.id,
    is_pinned: !!w.is_pinned,
    order,
  }))
}

function persist() {
  clearTimeout(saveTimer)
  saveTimer = setTimeout(async () => {
    if (saving) return
    saving = true
    setStatus('Đang lưu…', false)
    try {
      await action(route('wallets.update-sort'), 'POST', {
        wallets: collectPayload(),
      })
      setStatus('Đã lưu')
    } catch {
      setStatus('Lưu thất bại')
    } finally {
      saving = false
    }
  }, 180)
}

function normalizeOrder(preferFrontUnpinnedId: number | null = null) {
  const pinned = localWallets.value.filter((w) => w.is_pinned)
  let unpinned = localWallets.value.filter((w) => !w.is_pinned)
  if (preferFrontUnpinnedId) {
    const idx = unpinned.findIndex((w) => w.id === preferFrontUnpinnedId)
    if (idx >= 0) {
      const [front] = unpinned.splice(idx, 1)
      unpinned = [front, ...unpinned]
    }
  }
  localWallets.value = [...pinned, ...unpinned]
}

function inferPinned(walletId: number, newIndex: number) {
  const others = localWallets.value.filter((w) => w.id !== walletId)
  const otherPinned = others.filter((w) => w.is_pinned).length
  if (otherPinned === 0) return newIndex === 0
  return newIndex < otherPinned
}

function applyPin(walletId: number, pinned: boolean) {
  const w = localWallets.value.find((x) => x.id === walletId)
  if (w) w.is_pinned = pinned
}

function onDragEnd(evt: Sortable.SortableEvent) {
  const { oldIndex = 0, newIndex = 0, item } = evt
  if (oldIndex === newIndex && oldIndex === undefined) return

  const id = Number(item.dataset.id)
  const moved = localWallets.value.splice(oldIndex, 1)[0]
  localWallets.value.splice(newIndex, 0, moved)

  const wasPinned = moved.is_pinned
  const nowPinned = inferPinned(id, newIndex)
  applyPin(id, nowPinned)

  const justUnpinned = wasPinned && !nowPinned
  normalizeOrder(justUnpinned ? id : null)
  persist()
}

function togglePin(wallet: Wallet) {
  const wasPinned = wallet.is_pinned
  wallet.is_pinned = !wasPinned
  normalizeOrder(wasPinned ? wallet.id : null)
  persist()
}

function deleteWallet(wallet: Wallet) {
  if (!confirm('Xóa ví này?')) return
  router.delete(route('wallets.destroy', wallet.id))
}

function initSortable() {
  sortable?.destroy()
  if (!gridEl.value || !localWallets.value.length) return
  sortable = Sortable.create(gridEl.value, {
    animation: 240,
    easing: 'cubic-bezier(0.2, 0, 0, 1)',
    handle: '.wallet-handle',
    draggable: '.wallet-card',
    filter:
      'a, button:not(.wallet-handle):not(.wallet-pin-badge), input, form, textarea',
    preventOnFilter: false,
    ghostClass: 'sortable-ghost',
    chosenClass: 'sortable-chosen',
    dragClass: 'sortable-drag',
    forceFallback: false,
    fallbackOnBody: true,
    swapThreshold: 0.65,
    delayOnTouchOnly: true,
    delay: 120,
    onEnd: onDragEnd,
  })
}

onMounted(() => {
  syncFromProps()
  nextTick(initSortable)
})

onBeforeUnmount(() => {
  sortable?.destroy()
  clearTimeout(saveTimer)
  clearTimeout(statusTimer)
})
</script>

<template>
  <Head title="Quản lý ví" />
  <AppLayout title="Quản lý ví">
    <div class="page-header">
      <div>
        <h2 class="page-title">Ví của bạn</h2>
        <p class="page-subtitle">
          Kéo
          <span class="inline-block align-middle text-content-secondary"
            >⋮⋮</span
          >
          để sắp xếp. Ví được ghim hiện trên Tổng quan.
        </p>
      </div>
      <div class="flex items-center gap-3">
        <span
          class="text-xs text-content-muted transition-opacity duration-300"
          :class="sortStatusVisible ? 'opacity-100' : 'opacity-0'"
          aria-live="polite"
          >{{ sortStatus }}</span
        >
        <Link :href="route('wallets.create')" class="btn btn-primary">
          <NavIcon name="plus" size="w-4 h-4" />
          Thêm ví
        </Link>
      </div>
    </div>

    <section
      v-if="localWallets.length"
      class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6"
      aria-label="Tổng hợp"
    >
      <div
        class="col-span-2 rounded-2xl p-5 text-white bg-gradient-to-br from-primary-600 to-violet-600 dark:from-primary-600 dark:to-violet-700 shadow-lg shadow-primary-600/20"
      >
        <p class="text-sm/5 text-white/80">Tổng số dư khả dụng</p>
        <p class="mt-1 text-3xl font-bold tracking-tight tabular-nums">
          {{ formatMoney(summary.cash) }}
        </p>
        <p class="mt-2 text-xs text-white/70">
          {{ summary.activeCount }} ví đang dùng<template
            v-if="summary.inactiveCount"
          >
            · {{ summary.inactiveCount }} ví đã tắt</template
          >
        </p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-content-muted">Dư nợ thẻ</p>
        <p
          class="mt-1 text-lg font-bold tabular-nums"
          :class="
            summary.creditDebt > 0
              ? 'text-red-600 dark:text-red-400'
              : 'text-content'
          "
        >
          {{ formatMoney(summary.creditDebt) }}
        </p>
      </div>
      <div class="card p-4">
        <p class="text-xs text-content-muted">Hạn mức thẻ còn lại</p>
        <p class="mt-1 text-lg font-bold tabular-nums text-content">
          {{ formatMoney(summary.creditAvailable) }}
        </p>
      </div>
    </section>

    <div
      v-if="!localWallets.length"
      class="card flex flex-col items-center text-center px-6 py-14"
    >
      <span class="avatar w-14 h-14">
        <NavIcon name="wallet" size="w-7 h-7" />
      </span>
      <p class="mt-4 font-semibold text-content">Chưa có ví nào</p>
      <p class="mt-1 text-sm text-content-muted">
        Tạo ví tiền mặt, tài khoản ngân hàng, ví điện tử hoặc thẻ tín dụng.
      </p>
      <Link :href="route('wallets.create')" class="btn btn-primary mt-5">
        Tạo ví đầu tiên
      </Link>
    </div>

    <div
      v-else
      ref="gridEl"
      class="wallet-grid grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 sm:gap-4"
    >
      <article
        v-for="wallet in localWallets"
        :key="wallet.id"
        class="wallet-card card relative flex flex-col p-4 sm:p-5 select-none touch-manipulation"
        :class="{ 'opacity-60': !wallet.is_active }"
        :data-id="wallet.id"
        :data-pinned="wallet.is_pinned ? '1' : '0'"
      >
        <header class="flex items-start gap-3">
          <span
            class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0"
            :class="typeStyle(wallet.type).tone"
            aria-hidden="true"
          >
            <svg
              class="w-6 h-6"
              fill="none"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linecap="round"
              stroke-linejoin="round"
              viewBox="0 0 24 24"
            >
              <path :d="typeStyle(wallet.type).glyph" />
            </svg>
          </span>
          <div class="min-w-0 flex-1">
            <h3 class="font-semibold text-content truncate">
              {{ wallet.name }}
            </h3>
            <p class="text-xs text-content-muted truncate">
              {{ wallet.type_label
              }}<template v-if="!wallet.is_active"> · đã tắt</template>
            </p>
          </div>
          <button
            type="button"
            class="wallet-pin-badge icon-btn w-9 h-9 -mr-1"
            :class="
              wallet.is_pinned
                ? 'text-primary-600 dark:text-primary-400 hover:text-primary-700'
                : ''
            "
            :title="
              wallet.is_pinned
                ? 'Đã ghim · bấm để bỏ ghim'
                : 'Ghim lên Tổng quan'
            "
            :aria-label="wallet.is_pinned ? 'Bỏ ghim' : 'Ghim lên Tổng quan'"
            :aria-pressed="wallet.is_pinned ? 'true' : 'false'"
            @click.stop="togglePin(wallet)"
          >
            <svg
              class="w-5 h-5"
              :fill="wallet.is_pinned ? 'currentColor' : 'none'"
              stroke="currentColor"
              stroke-width="1.8"
              stroke-linejoin="round"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path
                d="M12 3l2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 17.8 6.2 20.9l1.1-6.5-4.7-4.6 6.5-.9z"
              />
            </svg>
          </button>
          <button
            type="button"
            class="wallet-handle icon-btn w-9 h-9 -mr-2 cursor-grab active:cursor-grabbing"
            title="Kéo để sắp xếp"
            aria-label="Kéo để sắp xếp"
          >
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
              <path
                d="M7 4a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm8-12a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"
              />
            </svg>
          </button>
        </header>

        <div class="mt-4">
          <template v-if="wallet.is_credit_card">
            <div class="flex items-baseline justify-between gap-2">
              <p class="text-xs text-content-muted">Dư nợ</p>
              <p
                class="text-xl font-bold tabular-nums text-red-600 dark:text-red-400"
              >
                {{ formatMoney(wallet.outstanding_balance ?? 0) }}
              </p>
            </div>
            <div
              class="mt-2 h-2 rounded-full bg-muted overflow-hidden"
              role="meter"
              :aria-valuenow="creditUsagePercent(wallet)"
              aria-valuemin="0"
              aria-valuemax="100"
              aria-label="Tỉ lệ hạn mức đã dùng"
            >
              <div
                class="h-full rounded-full"
                :class="
                  creditUsagePercent(wallet) >= 80
                    ? 'bg-red-500'
                    : 'bg-violet-500'
                "
                :style="{ width: `${creditUsagePercent(wallet)}%` }"
              />
            </div>
            <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-xs">
              <div>
                <dt class="text-content-muted">Còn lại</dt>
                <dd class="font-semibold tabular-nums text-content">
                  {{ formatMoney(wallet.spendable_balance ?? 0) }}
                </dd>
              </div>
              <div class="text-right">
                <dt class="text-content-muted">Hạn mức</dt>
                <dd class="font-semibold tabular-nums text-content-secondary">
                  {{ formatMoney(wallet.credit_limit ?? 0) }}
                </dd>
              </div>
              <div>
                <dt class="text-content-muted">Sao kê</dt>
                <dd class="font-medium text-content-secondary">
                  Ngày {{ wallet.statement_day }}
                </dd>
              </div>
              <div class="text-right">
                <dt class="text-content-muted">Thanh toán</dt>
                <dd class="font-medium text-content-secondary">
                  Ngày {{ wallet.payment_day }}
                </dd>
              </div>
            </dl>
          </template>
          <template v-else>
            <p class="text-xs text-content-muted">Số dư</p>
            <p
              class="text-2xl font-bold tracking-tight tabular-nums"
              :class="
                wallet.balance < 0
                  ? 'text-red-600 dark:text-red-400'
                  : 'text-content'
              "
            >
              {{ formatMoney(wallet.balance) }}
            </p>
          </template>
        </div>

        <p
          v-if="wallet.notes"
          class="mt-3 text-sm text-content-muted line-clamp-2"
        >
          {{ wallet.notes }}
        </p>

        <div class="flex-1 min-h-4" aria-hidden="true" />
        <footer
          class="pt-3 flex items-center gap-1 text-sm border-t border-subtle -mx-1"
        >
          <span class="flex-1 px-1 text-xs text-content-muted">
            {{ wallet.transactions_count ?? 0 }} giao dịch
          </span>
          <Link
            :href="route('wallets.edit', wallet.id)"
            class="px-3 py-1.5 rounded-lg font-medium text-primary-600 dark:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-900/40"
            >Sửa</Link
          >
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg font-medium btn-ghost-danger"
            @click="deleteWallet(wallet)"
          >
            Xóa
          </button>
        </footer>
      </article>
    </div>
  </AppLayout>
</template>

<style scoped>
.wallet-grid {
  grid-auto-flow: row;
}
.wallet-card {
  transition:
    transform 0.22s cubic-bezier(0.2, 0, 0, 1),
    box-shadow 0.22s ease,
    border-color 0.22s ease;
}
:global(.wallet-card.sortable-ghost) {
  opacity: 0.35;
  transform: scale(0.98);
}
:global(.wallet-card.sortable-chosen) {
  box-shadow: 0 12px 28px -8px rgb(0 0 0 / 0.25);
  z-index: 20;
}
:global(.wallet-card.sortable-drag) {
  opacity: 1 !important;
  transform: scale(1.02) rotate(0.6deg);
  box-shadow: 0 18px 40px -12px rgb(79 70 229 / 0.35);
  border-color: rgb(99 102 241);
}
</style>
