<script setup lang="ts">
import type { PropType } from 'vue'
import type { Wallet, Template, Prefill } from '@/domain/entities'
import { computed, ref, watch } from 'vue'
import { Head, Link, useForm } from '@/application/navigation'
import {
  bankHandoffAvailable,
  planBankHandoff,
  startBankHandoff,
} from '@/application/bank-handoff'
import { copyText } from '@/infrastructure/device'
import QrScanner from '@/Components/QrScanner.vue'
import NavIcon from '@/Components/NavIcon.vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import MoneyInput from '@/Components/MoneyInput.vue'
import DatePicker from '@/Components/DatePicker.vue'
import AppSelect from '@/Components/AppSelect.vue'
import type { AppSelectOption } from '@/Components/AppSelect.vue'
import {
  formatMoney,
  maskAccountNumber,
  parseVietQr,
  todayVi,
  VietQrError,
  walletBank,
  type VietQrTransfer,
} from '@/domain'

const props = defineProps({
  wallets: { type: Array as PropType<Wallet[]>, default: () => [] },
  templates: { type: Array as PropType<Template[]>, default: () => [] },
  selectedTemplateId: { type: [Number, String], default: null },
  prefill: { type: Object as PropType<Prefill>, default: () => ({}) },
  walletBalances: {
    type: Object as PropType<Record<string, number>>,
    default: () => ({}),
  },
})

const defaultWalletId =
  props.prefill.wallet_id ??
  props.templates.find((t) => t.id === props.selectedTemplateId)
    ?.default_wallet_id ??
  props.wallets[0]?.id ??
  ''

const form = useForm({
  transaction_template_id: props.selectedTemplateId ?? '',
  wallet_id: defaultWalletId,
  from_wallet_id: '' as number | string,
  to_wallet_id: '' as number | string,
  type: props.prefill.type ?? 'expense',
  amount: props.prefill.amount ?? '',
  target_balance: '' as number | string,
  fee: 0,
  description: props.prefill.description ?? '',
  category: props.prefill.category ?? '',
  transacted_at: props.prefill.transacted_at ?? todayVi(),
  note: '',
  recurring_item_id: props.prefill.recurring_item_id ?? '',
  recurring_occurrence_id: props.prefill.recurring_occurrence_id ?? '',
  save_as_template: false,
  template_name: '',
})

const fromTemplate = computed(() => !!form.transaction_template_id)
const selectedTemplateSelect = computed({
  get: () =>
    form.transaction_template_id ? String(form.transaction_template_id) : '',
  set: (v) => applyTemplate(v),
})

const currentBalance = computed(() => {
  const bal = props.walletBalances[String(form.wallet_id)]
  return bal == null ? 0 : Number(bal)
})

const adjustmentDelta = computed(() => {
  if (form.target_balance === '' || form.target_balance === null) return null
  return Math.round(Number(form.target_balance) - currentBalance.value)
})

const adjustmentPreview = computed(() => {
  const delta = adjustmentDelta.value
  if (delta === null || !Number.isFinite(delta) || delta === 0) return ''
  const sign = delta > 0 ? '+' : '−'
  return sign + formatMoney(Math.abs(delta))
})

const transferTotal = computed(
  () => (Number(form.amount) || 0) + (Number(form.fee) || 0),
)

const templateOptions = computed((): AppSelectOption[] => [
  { value: '', label: '— Không dùng mẫu —' },
  ...props.templates.map((t) => ({
    value: String(t.id),
    label: `${t.name} (${t.type_label})`,
  })),
])

const transactionTypeOptions: AppSelectOption[] = [
  { value: 'expense', label: 'Chi' },
  { value: 'income', label: 'Thu' },
  { value: 'adjustment', label: 'Cân đối' },
  { value: 'transfer', label: 'Chuyển / Rút ví' },
]

const walletNameOptions = computed((): AppSelectOption[] =>
  props.wallets.map((w) => ({ value: w.id, label: w.name })),
)

const walletBalanceOptions = computed((): AppSelectOption[] =>
  props.wallets.map((w) => ({
    value: w.id,
    label: `${w.name} (${formatMoney(w.balance)})`,
  })),
)

function applyTemplate(id: string | number) {
  if (!id) {
    form.transaction_template_id = ''
    return
  }
  const t = props.templates.find((x) => String(x.id) === String(id))
  if (!t) return
  form.transaction_template_id = t.id
  form.type = t.type
  form.amount = t.amount
  form.fee = t.fee || 0
  form.description = t.description || t.name
  form.category = t.category || ''
  if (t.default_wallet_id) form.wallet_id = t.default_wallet_id
  if (t.from_wallet_id) form.from_wallet_id = t.from_wallet_id
  if (t.to_wallet_id) form.to_wallet_id = t.to_wallet_id
  if (t.type === 'adjustment') {
    const signed =
      (t.adjustment_direction === 'decrease' ? -1 : 1) * Number(t.amount || 0)
    form.target_balance = currentBalance.value + signed
  }
}

if (props.selectedTemplateId) {
  applyTemplate(props.selectedTemplateId)
}

watch(
  () => form.wallet_id,
  () => {
    if (form.type === 'adjustment' && form.transaction_template_id) {
      const t = props.templates.find(
        (x) => x.id === form.transaction_template_id,
      )
      if (t?.type === 'adjustment') {
        const signed =
          (t.adjustment_direction === 'decrease' ? -1 : 1) *
          Number(t.amount || 0)
        form.target_balance = currentBalance.value + signed
      }
    }
  },
)

// ---- Bank app handoff + VietQR scan -------------------------------------
const canHandoff = bankHandoffAvailable()
const qr = ref<VietQrTransfer | null>(null)
const scannerOpen = ref(String(props.prefill.scan ?? '') === '1')
const openBankApp = ref(true)
const copied = ref('')

const bankWallets = computed(() =>
  props.wallets.filter((w) => w.type === 'bank'),
)
const walletById = (id: unknown) =>
  props.wallets.find((w) => String(w.id) === String(id))

/** Wallet the money leaves from, when that can be a bank transfer. */
const sourceWallet = computed(() =>
  form.type === 'expense'
    ? walletById(form.wallet_id)
    : form.type === 'transfer'
      ? walletById(form.from_wallet_id)
      : undefined,
)

const qrTarget = computed(() =>
  qr.value && form.type === 'expense'
    ? {
        bank: qr.value.bank,
        accountNumber: qr.value.accountNumber,
        amount: Number(form.amount) || null,
        message: form.description,
      }
    : undefined,
)

const handoff = computed(() =>
  canHandoff ? planBankHandoff(sourceWallet.value, qrTarget.value) : undefined,
)

/** Bank wallet without (supported) bank details: hint to complete it. */
const missingBankInfo = computed(
  () =>
    canHandoff &&
    sourceWallet.value?.type === 'bank' &&
    !handoff.value &&
    !walletBank(sourceWallet.value),
)

function walletHasApp(id: unknown) {
  const w = walletById(id)
  return !!w && !!walletBank(w)?.app
}

function acceptQr(raw: string): true | string {
  let parsed: VietQrTransfer
  try {
    parsed = parseVietQr(raw)
  } catch (e) {
    return e instanceof VietQrError ? e.message : 'Không đọc được mã QR.'
  }
  qr.value = parsed
  scannerOpen.value = false
  form.transaction_template_id = ''
  form.type = 'expense'
  if (parsed.amount) form.amount = parsed.amount
  const who =
    parsed.merchantName ||
    `${parsed.bank?.shortName ?? parsed.bin} ${maskAccountNumber(parsed.accountNumber)}`
  form.description = parsed.message || `Chuyển khoản ${who}`
  if (!form.category) form.category = 'Chuyển khoản'
  if (!form.note)
    form.note = [
      'Người nhận:',
      parsed.merchantName,
      `${parsed.bank?.shortName ?? parsed.bin} ${parsed.accountNumber}`,
    ]
      .filter(Boolean)
      .join(' ')
  // Prefer a bank wallet whose app we can open, like e-wallet "pay by QR".
  const current = walletById(form.wallet_id)
  if (current?.type !== 'bank' || !walletHasApp(current.id)) {
    const pick =
      bankWallets.value.find((w) => w.is_active && walletHasApp(w.id)) ??
      bankWallets.value.find((w) => w.is_active)
    if (pick) form.wallet_id = pick.id
  }
  return true
}

function clearQr() {
  qr.value = null
}

async function copy(value: string, label: string) {
  if (await copyText(value)) {
    copied.value = label
    setTimeout(() => (copied.value = ''), 1500)
  }
}

function submit() {
  const plan = openBankApp.value ? handoff.value : undefined
  // Apps without autofill need the account number pasted: copy it now,
  // while we're still inside the tap (clipboard needs a user gesture).
  if (plan && qr.value && !plan.autofill) void copyText(qr.value.accountNumber)
  form.post(route('transactions.store'), {
    onSuccess: () => {
      if (plan) startBankHandoff(plan)
    },
  })
}
</script>

<template>
  <Head title="Ghi giao dịch" />
  <AppLayout title="Ghi giao dịch">
    <div class="max-w-2xl mx-auto">
      <div class="mb-6 flex items-center justify-between gap-3">
        <h2 class="text-2xl font-bold text-content">Ghi giao dịch</h2>
        <div class="flex items-center gap-3">
          <button
            type="button"
            class="btn btn-secondary px-3"
            @click="scannerOpen = true"
          >
            <NavIcon name="qr" size="w-4 h-4" />
            Quét QR
          </button>
          <Link
            :href="route('transactions.bulk')"
            class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline"
            >Ghi cả ngày →</Link
          >
        </div>
      </div>

      <form
        class="bg-surface shadow rounded-lg p-6 space-y-4"
        @submit.prevent="submit"
      >
        <!-- Recipient from a scanned VietQR -->
        <section
          v-if="qr"
          class="rounded-xl border border-primary-200 dark:border-primary-800 bg-primary-50/60 dark:bg-primary-900/20 p-4"
          aria-label="Người nhận"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p
                class="text-xs font-medium uppercase tracking-wide text-primary-700 dark:text-primary-300"
              >
                Chuyển khoản đến
              </p>
              <p
                v-if="qr.merchantName"
                class="mt-1 font-semibold text-content truncate"
              >
                {{ qr.merchantName }}
              </p>
              <p class="text-sm text-content-secondary">
                {{ qr.bank?.shortName ?? `BIN ${qr.bin}` }}
                <span v-if="qr.service === 'card'"> · Thẻ</span>
              </p>
              <button
                type="button"
                class="mt-1 font-mono text-lg font-semibold tracking-wide text-content hover:underline"
                title="Sao chép số tài khoản"
                @click="copy(qr.accountNumber, 'account')"
              >
                {{ qr.accountNumber }}
              </button>
              <span
                v-if="copied === 'account'"
                class="ml-2 text-xs text-primary-700 dark:text-primary-300"
                >Đã sao chép</span
              >
              <p v-if="qr.amount" class="text-sm text-content-secondary">
                Số tiền trong mã:
                <strong>{{ formatMoney(qr.amount) }}</strong>
              </p>
            </div>
            <button
              type="button"
              class="text-sm text-content-muted hover:text-content"
              @click="clearQr"
            >
              Bỏ
            </button>
          </div>

          <div class="mt-4">
            <p class="text-sm font-medium text-content-secondary">
              Chuyển từ tài khoản
            </p>
            <div
              v-if="bankWallets.length"
              class="mt-2 space-y-2"
              role="radiogroup"
              aria-label="Tài khoản nguồn"
            >
              <label
                v-for="w in bankWallets"
                :key="w.id"
                class="source-option"
                :class="{
                  'source-option-active':
                    String(form.wallet_id) === String(w.id),
                  'opacity-60': !w.is_active,
                }"
              >
                <input
                  v-model="form.wallet_id"
                  type="radio"
                  name="source_wallet"
                  :value="w.id"
                  class="sr-only"
                />
                <span class="min-w-0 flex-1">
                  <span class="block font-medium text-content truncate">{{
                    w.name
                  }}</span>
                  <span class="block text-xs text-content-muted truncate">
                    <template v-if="walletBank(w)"
                      >{{ walletBank(w)?.shortName }}
                      {{ maskAccountNumber(w.account_number) }}</template
                    >
                    <template v-else>Chưa có thông tin ngân hàng</template>
                  </span>
                </span>
                <span class="text-sm font-semibold tabular-nums text-content">{{
                  formatMoney(w.balance)
                }}</span>
              </label>
            </div>
            <p v-else class="mt-1 text-sm text-content-muted">
              Chưa có ví tài khoản ngân hàng.
              <Link
                :href="route('wallets.create')"
                class="text-primary-600 dark:text-primary-400 underline"
                >Thêm ví</Link
              >
              hoặc chọn ví bên dưới.
            </p>
          </div>
        </section>

        <div v-if="!qr">
          <label class="block text-sm font-medium text-content-secondary"
            >Chọn mẫu (tùy chọn)</label
          >
          <AppSelect
            v-model="selectedTemplateSelect"
            class="mt-1"
            :options="templateOptions"
            :searchable="true"
            search-placeholder="Tìm..."
          />
        </div>

        <div v-if="!qr">
          <label class="block text-sm font-medium text-content-secondary"
            >Loại giao dịch</label
          >
          <AppSelect
            v-model="form.type"
            class="mt-1"
            :options="transactionTypeOptions"
            :searchable="false"
            required
          />
        </div>

        <!-- Thu / Chi -->
        <div
          v-if="form.type === 'income' || form.type === 'expense'"
          class="space-y-4"
        >
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div v-if="!qr || !bankWallets.length">
              <label class="block text-sm font-medium text-content-secondary"
                >Ví</label
              >
              <AppSelect
                v-model="form.wallet_id"
                class="mt-1"
                :options="walletNameOptions"
                placeholder="Chọn ví"
                :searchable="true"
                required
                search-placeholder="Tìm..."
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-content-secondary"
                >Số tiền (₫)</label
              >
              <div class="field-money">
                <MoneyInput v-model="form.amount" required />
              </div>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-content-secondary"
              >Mô tả</label
            >
            <input
              v-model="form.description"
              type="text"
              required
              class="form-input"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-content-secondary"
              >Danh mục</label
            >
            <input v-model="form.category" type="text" class="form-input" />
          </div>
        </div>

        <!-- Cân đối -->
        <div
          v-if="form.type === 'adjustment'"
          class="space-y-4 rounded-lg border border-amber-200 bg-amber-50/30 dark:bg-amber-900/20 p-4"
        >
          <p class="text-xs text-amber-800 dark:text-amber-200">
            Nhập số dư cuối cùng cần khớp. Hệ thống tự tính mức tăng/giảm so với
            số dư hiện tại.
          </p>
          <div>
            <label class="block text-sm font-medium text-content-secondary"
              >Ví</label
            >
            <AppSelect
              v-model="form.wallet_id"
              class="mt-1"
              :options="walletBalanceOptions"
              :searchable="true"
              required
              search-placeholder="Tìm..."
            />
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-content-secondary"
                >Số dư hiện tại</label
              >
              <p
                class="mt-1 block w-full rounded-md border border-subtle bg-app text-content p-2 text-sm font-semibold"
              >
                {{ formatMoney(currentBalance) }}
              </p>
            </div>
            <div>
              <label class="block text-sm font-medium text-content-secondary"
                >Số dư cuối cùng (₫)</label
              >
              <div class="field-money">
                <MoneyInput v-model="form.target_balance" required />
              </div>
              <p
                v-if="form.errors.target_balance"
                class="mt-1 text-xs text-red-600"
              >
                {{ form.errors.target_balance }}
              </p>
            </div>
          </div>
          <p
            v-if="adjustmentPreview"
            class="text-xs text-amber-900 dark:text-amber-200"
          >
            Điều chỉnh:
            <span class="font-semibold">{{ adjustmentPreview }}</span>
          </p>
          <div>
            <label class="block text-sm font-medium text-content-secondary"
              >Lý do</label
            >
            <input
              v-model="form.description"
              type="text"
              required
              placeholder="VD: Đối chiếu sao kê tháng 5"
              class="form-input"
            />
          </div>
        </div>

        <!-- Chuyển ví -->
        <div
          v-if="form.type === 'transfer'"
          class="space-y-4 rounded-lg border border-blue-200 bg-blue-50/30 dark:bg-blue-900/20 p-4"
        >
          <p class="text-xs text-blue-800 dark:text-blue-200">
            Rút/chuyển từ ví nguồn sang ví đích. Phí trừ thêm ở ví nguồn.
          </p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-content-secondary"
                >Từ ví</label
              >
              <AppSelect
                v-model="form.from_wallet_id"
                class="mt-1"
                :options="walletNameOptions"
                placeholder="Chọn ví nguồn"
                :searchable="true"
                required
                search-placeholder="Tìm..."
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-content-secondary"
                >Đến ví</label
              >
              <AppSelect
                v-model="form.to_wallet_id"
                class="mt-1"
                :options="walletNameOptions"
                placeholder="Chọn ví đích"
                :searchable="true"
                required
                search-placeholder="Tìm..."
              />
            </div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-content-secondary"
                >Số tiền chuyển (₫)</label
              >
              <div class="field-money">
                <MoneyInput v-model="form.amount" required />
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-content-secondary"
                >Phí (₫)</label
              >
              <div class="field-money"><MoneyInput v-model="form.fee" /></div>
            </div>
          </div>
          <p class="text-xs text-gray-600 dark:text-slate-500">
            Ví nguồn trừ:
            <span class="font-semibold">{{ formatMoney(transferTotal) }}</span>
          </p>
          <div>
            <label class="block text-sm font-medium text-content-secondary"
              >Mô tả</label
            >
            <input
              v-model="form.description"
              type="text"
              required
              placeholder="VD: Chuyển tiền mặt sang ngân hàng"
              class="form-input"
            />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-content-secondary"
            >Ngày giao dịch</label
          >
          <div class="field-date">
            <DatePicker v-model="form.transacted_at" required />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-content-secondary"
            >Ghi chú</label
          >
          <textarea v-model="form.note" rows="2" class="form-input" />
        </div>

        <div
          v-if="!fromTemplate"
          class="rounded-md bg-app p-4 border border-default"
        >
          <label class="flex items-center gap-2 text-sm text-content-secondary">
            <input
              v-model="form.save_as_template"
              type="checkbox"
              value="1"
              class="rounded border-strong bg-surface text-primary-600 dark:text-primary-400"
            />
            Lưu thành mẫu
          </label>
          <div v-if="form.save_as_template" class="mt-3">
            <input
              v-model="form.template_name"
              type="text"
              placeholder="Tên mẫu"
              class="form-input"
            />
          </div>
        </div>

        <!-- Hand off to the bank app (phones only) -->
        <div
          v-if="handoff"
          class="rounded-md border border-sky-200 dark:border-sky-800 bg-sky-50/50 dark:bg-sky-900/20 p-4"
        >
          <label class="flex items-start gap-2 text-sm text-content-secondary">
            <input
              v-model="openBankApp"
              type="checkbox"
              class="mt-0.5 rounded border-strong bg-surface text-primary-600"
            />
            <span>
              Lưu xong mở app <strong>{{ handoff.appName }}</strong> để chuyển
              khoản
              <span v-if="qrTarget" class="block text-xs text-content-muted">{{
                handoff.autofill
                  ? 'App sẽ điền sẵn người nhận và số tiền.'
                  : 'Số tài khoản người nhận sẽ được sao chép để dán vào app.'
              }}</span>
            </span>
          </label>
        </div>
        <p v-else-if="missingBankInfo" class="text-xs text-content-muted">
          Thêm ngân hàng và số tài khoản cho ví này để mở app ngân hàng sau khi
          lưu.
          <Link
            :href="route('wallets.edit', sourceWallet?.id ?? 0)"
            class="text-primary-600 dark:text-primary-400 underline"
            >Cập nhật ví</Link
          >
        </p>

        <div class="flex gap-3 pt-2">
          <button
            type="submit"
            class="px-4 py-2 bg-primary-600 dark:bg-primary-500 text-white rounded-md text-sm font-medium hover:bg-primary-700 dark:hover:bg-primary-600 disabled:opacity-50"
            :disabled="form.processing"
          >
            {{ handoff && openBankApp ? 'Lưu & mở app' : 'Lưu' }}
          </button>
          <Link
            :href="route('transactions.index')"
            class="px-4 py-2 border border-strong bg-surface text-content rounded-md text-sm text-content-secondary hover:bg-surface-hover"
            >Hủy</Link
          >
        </div>
      </form>
    </div>
    <QrScanner
      :open="scannerOpen"
      title="Quét mã chuyển khoản"
      :accept="acceptQr"
      @close="scannerOpen = false"
    />
  </AppLayout>
</template>

<style scoped>
@reference '../../../styles.css';
.source-option {
  @apply flex items-center gap-3 rounded-xl border border-default bg-surface px-3 py-2.5 cursor-pointer transition-colors hover:bg-surface-hover;
}
.source-option:has(input:focus-visible) {
  @apply ring-2 ring-primary-500 ring-offset-2 ring-offset-surface;
}
.source-option-active {
  @apply border-primary-500 bg-primary-50 dark:bg-primary-900/40 hover:bg-primary-50 dark:hover:bg-primary-900/40;
}
</style>
