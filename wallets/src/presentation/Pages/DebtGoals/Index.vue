<script setup lang="ts">
import { computed, watch } from 'vue'
import { Head, Link, useForm } from '@/application/navigation'
import AppLayout from '@/Layouts/AppLayout.vue'
import MoneyInput from '@/Components/MoneyInput.vue'
import AppSelect from '@/Components/AppSelect.vue'
import type { AppSelectOption } from '@/Components/AppSelect.vue'
import { formatMoney } from '@/domain'

type WalletOption = {
  id: number
  name: string
  type: string
  type_label: string
  balance: number
}

type LoanOption = {
  id: number
  name: string
  principal_amount: number
  term_months: number
  started_at: string | null
}

type FeeRule = {
  year: number
  type: string
  value: number | string
}

type FeeTypeOption = { value: string; label: string }

type Progress = {
  name: string
  target_amount: number
  collected: number
  percent: number
  remaining: number
  wallet_name: string
  reached: boolean
  has_loan: boolean
  loan_name?: string | null
  principal_remaining?: number
  settlement_interest?: number
  settlement_fee?: number
  current_year?: number
} | null

const props = defineProps<{
  goal: {
    name: string
    target_amount: number | null
    wallet_id: number | null
    loan_id: number | null
    settlement_fees: FeeRule[]
  }
  wallets: WalletOption[]
  loans: LoanOption[]
  fee_types: FeeTypeOption[]
  progress: Progress
}>()

const form = useForm({
  name: props.goal.name || 'Mục tiêu trả nợ',
  target_amount: props.goal.target_amount ?? ('' as number | string),
  wallet_id: props.goal.wallet_id ?? props.wallets[0]?.id ?? '',
  loan_id: props.goal.loan_id ?? ('' as number | string),
  settlement_fees: (props.goal.settlement_fees?.length
    ? props.goal.settlement_fees.map((r) => ({ ...r }))
    : [{ year: 1, type: 'percent', value: 0 }]) as FeeRule[],
  clear: false,
})

const hasLoan = computed((): boolean => Number(form.loan_id) > 0)

const selectedLoan = computed((): LoanOption | undefined =>
  props.loans.find((l) => String(l.id) === String(form.loan_id)),
)

const walletOptions = computed((): AppSelectOption[] =>
  props.wallets.map((w) => ({
    value: w.id,
    label: `${w.name} (${formatMoney(w.balance, false)} ₫) · ${w.type_label}`,
  })),
)

const loanOptions = computed((): AppSelectOption[] => [
  { value: '', label: '— Không gắn, nhập tay số tiền —' },
  ...props.loans.map((loan) => ({
    value: loan.id,
    label: `${loan.name} · gốc ${formatMoney(loan.principal_amount, false)} ₫`,
  })),
])

watch(hasLoan, (linked) => {
  if (linked) {
    form.target_amount = ''
    if (!form.settlement_fees.length) {
      form.settlement_fees.push({ year: 1, type: 'percent', value: 0 })
    }
  }
})

function addFeeRule(): void {
  const maxYear = form.settlement_fees.reduce(
    (max, r) => Math.max(max, Number(r.year) || 0),
    0,
  )
  form.settlement_fees.push({ year: maxYear + 1, type: 'percent', value: 0 })
}

function removeFeeRule(index: number): void {
  form.settlement_fees.splice(index, 1)
}

function submit(): void {
  form.clear = false
  form
    .transform((data) => ({
      ...data,
      loan_id:
        data.loan_id === '' || data.loan_id === null
          ? null
          : Number(data.loan_id),
      target_amount: hasLoan.value ? null : data.target_amount,
      settlement_fees: data.settlement_fees.map((r) => ({
        year: Number(r.year),
        type: r.type,
        value: Number(r.value) || 0,
      })),
    }))
    .post(route('debt-goals.update'))
}

function clearGoal(): void {
  if (!confirm('Xóa mục tiêu trả nợ?')) {
    return
  }
  form.clear = true
  form.post(route('debt-goals.update'))
}
</script>

<template>
  <Head title="Mục tiêu trả nợ" />
  <AppLayout title="Mục tiêu trả nợ">
    <div class="mb-6">
      <h2 class="text-2xl font-bold leading-7 text-content sm:text-3xl">
        Kế hoạch tài chính
      </h2>
    </div>

    <div class="flex flex-wrap border-b border-subtle mb-6">
      <Link
        :href="route('loans.index')"
        class="px-4 py-2 border-b-2 border-transparent text-content-muted hover:text-content font-medium text-sm"
        >Khoản vay & Nợ</Link
      >
      <Link
        :href="route('recurring-items.index')"
        class="px-4 py-2 border-b-2 border-transparent text-content-muted hover:text-content font-medium text-sm"
        >Thu chi cố định</Link
      >
      <Link
        :href="route('fixed-expenses.index')"
        class="px-4 py-2 border-b-2 border-transparent text-content-muted hover:text-content font-medium text-sm"
        >Tổng hợp chi cố định</Link
      >
      <Link
        :href="route('debt-goals.index')"
        class="px-4 py-2 border-b-2 border-primary-600 dark:border-primary-400 text-primary-600 dark:text-primary-400 font-semibold text-sm"
        >Mục tiêu trả nợ</Link
      >
    </div>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <p class="text-sm text-content-muted max-w-2xl">
        Gắn ví gom tiền và (tuỳ chọn) khoản vay. Khi gắn vay, mục tiêu = dư nợ
        còn lại + phí tất toán theo năm. Thẻ tiến độ hiện dưới header trên mọi
        trang.
      </p>
      <Link
        v-if="progress?.has_loan"
        :href="route('debt-goals.show')"
        class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline"
      >
        Xem chi tiết tất toán →
      </Link>
    </div>

    <form
      class="bg-surface shadow rounded-lg border border-default p-4 sm:p-6 space-y-5 max-w-2xl"
      @submit.prevent="submit"
    >
      <div>
        <label class="block text-sm font-medium text-content-secondary"
          >Tên mục tiêu</label
        >
        <input
          v-model="form.name"
          type="text"
          maxlength="120"
          placeholder="Mục tiêu trả nợ"
          class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2"
        />
      </div>

      <div>
        <label class="block text-sm font-medium text-content-secondary"
          >Ví gom tiền</label
        >
        <AppSelect
          v-model="form.wallet_id"
          class="mt-1"
          :options="walletOptions"
          placeholder="Chưa có ví phù hợp"
          :searchable="true"
          required
          search-placeholder="Tìm..."
        />
        <p class="mt-1 text-xs text-content-muted">Số dư ví = tiền đã gom.</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-content-secondary"
          >Khoản vay (tuỳ chọn)</label
        >
        <AppSelect
          v-model="form.loan_id"
          class="mt-1"
          :options="loanOptions"
          :searchable="true"
          search-placeholder="Tìm..."
        />
        <p v-if="selectedLoan" class="mt-1 text-xs text-content-muted">
          {{ selectedLoan.term_months }} tháng · bắt đầu
          {{ selectedLoan.started_at || '—' }}
        </p>
      </div>

      <div>
        <label class="block text-sm font-medium text-content-secondary"
          >Số tiền mục tiêu</label
        >
        <div class="field-money">
          <MoneyInput
            v-model="form.target_amount"
            :required="!hasLoan"
            :readonly="hasLoan"
          />
        </div>
        <p
          v-if="hasLoan"
          class="mt-1 text-xs text-amber-700 dark:text-amber-300"
        >
          Đã gắn khoản vay — mục tiêu lấy từ dư nợ + phí tất toán hiện tại
          (không nhập tay).
        </p>
        <p v-if="form.errors.target_amount" class="mt-1 text-sm text-red-600">
          {{ form.errors.target_amount }}
        </p>
      </div>

      <div class="border-t border-subtle pt-5">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
          <div>
            <h3 class="text-sm font-semibold text-content">
              Phí tất toán theo năm
            </h3>
            <p class="text-xs text-content-muted mt-0.5">
              Năm 1 = 12 tháng đầu của khoản vay. Không có rule → phí 0.
            </p>
          </div>
          <button
            type="button"
            class="text-sm font-medium text-primary-600 dark:text-primary-400"
            @click="addFeeRule"
          >
            + Thêm năm
          </button>
        </div>

        <div class="space-y-3">
          <div
            v-for="(rule, index) in form.settlement_fees"
            :key="index"
            class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end"
          >
            <div>
              <label class="block text-xs font-medium text-content-muted"
                >Năm</label
              >
              <input
                v-model.number="rule.year"
                type="number"
                min="1"
                max="40"
                required
                class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2 text-sm"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-content-muted"
                >Loại</label
              >
              <AppSelect
                v-model="rule.type"
                class="mt-1"
                :options="fee_types"
                :searchable="false"
                size="sm"
              />
            </div>
            <div>
              <label class="block text-xs font-medium text-content-muted">
                {{ rule.type === 'percent' ? 'Phần trăm (%)' : 'Số tiền (₫)' }}
              </label>
              <div v-if="rule.type === 'fixed'" class="field-money">
                <MoneyInput v-model="rule.value" />
              </div>
              <input
                v-else
                v-model.number="rule.value"
                type="number"
                min="0"
                step="0.01"
                class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2 text-sm"
              />
            </div>
            <div>
              <button
                type="button"
                class="w-full sm:w-auto px-3 py-2 text-sm text-red-600 dark:text-red-400 border border-strong rounded-md hover:bg-surface-hover"
                @click="removeFeeRule(index)"
              >
                Xóa
              </button>
            </div>
          </div>
          <p
            v-if="!form.settlement_fees.length"
            class="text-sm text-content-muted"
          >
            Chưa có phí tất toán.
          </p>
        </div>
      </div>

      <div
        v-if="progress"
        class="rounded-lg bg-app border border-subtle p-3 text-sm space-y-1"
      >
        <p class="text-content-muted">Tiến độ hiện tại</p>
        <p class="font-medium text-content">
          Đã gom {{ formatMoney(progress.collected, false) }} ₫ / tổng
          {{ formatMoney(progress.target_amount, false) }} ₫
          <span class="text-primary-600 dark:text-primary-400"
            >({{ progress.percent }}%)</span
          >
        </p>
        <p v-if="progress.has_loan" class="text-xs text-content-muted">
          Gốc còn {{ formatMoney(progress.principal_remaining ?? 0, false) }} ₫
          · lãi kỳ TT
          {{ formatMoney(progress.settlement_interest ?? 0, false) }} ₫ · phí
          năm {{ progress.current_year }}:
          {{ formatMoney(progress.settlement_fee ?? 0, false) }} ₫ ·
          {{ progress.loan_name }}
        </p>
      </div>

      <div class="flex flex-wrap gap-3 pt-1">
        <button
          type="submit"
          class="px-4 py-2 bg-primary-600 dark:bg-primary-500 text-white rounded-md text-sm font-medium disabled:opacity-50"
          :disabled="form.processing || !wallets.length"
        >
          Lưu mục tiêu
        </button>
        <button
          v-if="progress"
          type="button"
          class="px-4 py-2 border border-strong text-content-secondary rounded-md text-sm font-medium hover:bg-surface-hover"
          :disabled="form.processing"
          @click="clearGoal"
        >
          Xóa mục tiêu
        </button>
      </div>
    </form>
  </AppLayout>
</template>
