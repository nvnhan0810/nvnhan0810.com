<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@/application/navigation'
import AppLayout from '@/Layouts/AppLayout.vue'
import { formatDateVi, formatMoney } from '@/domain'

type SettlementRow = {
  month_index: number
  due_date: string
  year: number
  payment: number
  principal: number
  interest: number
  fee: number
  days: number | null
  principal_before: number
  remaining_principal: number
  settlement_interest: number
  settlement_fee: number
  settlement_total: number
  progress_percent: number
  can_settle: boolean
  is_paid: boolean
  is_current: boolean
}

const props = defineProps<{
  goal_name: string
  loan: {
    id: number
    name: string
    type: string
    principal_amount: number
    interest_rate: number
    interest_calculation_method: string
    term_months: number
    started_at: string | null
    monthly_payment: number
    collection_fee: number
  }
  wallet: { id: number; name: string; balance: number } | null
  months_passed: number
  payment_day: number | null
  current: {
    principal_remaining: number
    settlement_interest: number
    settlement_fee: number
    settlement_total: number
    year: number
    month_index: number
  } | null
  collected: number
  percent: number
  rows: SettlementRow[]
  fees: Array<{ year: number; type: string; value: number }>
}>()

const method = computed(
  () => props.loan.interest_calculation_method ?? 'monthly',
)
const showDays = computed(
  () => method.value === 'daily' || method.value === 'homecredit',
)
const showPeriodFee = computed(
  () => method.value === 'custom' || method.value === 'homecredit',
)

function feeLabel(rule: { type: string; value: number }): string {
  if (rule.type === 'fixed') {
    return formatMoney(rule.value, false) + ' ₫'
  }
  return `${rule.value}%`
}

function rowClass(row: SettlementRow): string {
  if (row.is_paid) {
    return 'bg-green-50/80 dark:bg-green-900/20'
  }
  if (row.is_current) {
    return 'bg-primary-50/80 dark:bg-primary-900/20'
  }
  if (row.can_settle) {
    return 'bg-emerald-50/50 dark:bg-emerald-900/10'
  }
  return ''
}
</script>

<template>
  <Head :title="`Chi tiết: ${goal_name}`" />
  <AppLayout :title="goal_name">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
      <div>
        <h2 class="text-2xl font-bold text-content">{{ goal_name }}</h2>
        <p class="mt-1 text-sm text-content-muted">
          Lịch tất toán theo tháng · {{ loan.name }}
        </p>
      </div>
      <div class="flex flex-wrap gap-3 text-sm">
        <Link
          :href="route('debt-goals.index')"
          class="text-primary-600 dark:text-primary-400 font-medium hover:underline"
          >Cài đặt</Link
        >
        <Link
          :href="route('loans.show', loan.id)"
          class="text-content-muted hover:text-content"
          >Khoản vay →</Link
        >
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="bg-surface border border-default rounded-lg p-4 shadow-sm">
        <p
          class="text-xs text-content-muted uppercase tracking-wider font-semibold"
        >
          Tổng tất toán hiện tại
        </p>
        <p class="mt-1 text-xl font-bold text-content">
          {{ formatMoney(current?.settlement_total ?? 0, false) }} ₫
        </p>
        <p class="text-xs text-content-muted mt-1">
          Năm {{ current?.year ?? '—' }} của khoản vay
        </p>
      </div>
      <div class="bg-surface border border-default rounded-lg p-4 shadow-sm">
        <p
          class="text-xs text-content-muted uppercase tracking-wider font-semibold"
        >
          Đã gom
        </p>
        <p
          class="mt-1 text-xl font-bold text-primary-600 dark:text-primary-400"
        >
          {{ formatMoney(collected, false) }} ₫
        </p>
        <p class="text-xs text-content-muted mt-1">
          Ví: {{ wallet?.name ?? '—' }}
        </p>
      </div>
      <div class="bg-surface border border-default rounded-lg p-4 shadow-sm">
        <p
          class="text-xs text-content-muted uppercase tracking-wider font-semibold"
        >
          Tiến độ
        </p>
        <p class="mt-1 text-xl font-bold text-content">{{ percent }}%</p>
        <div
          class="mt-2 h-2 rounded-full bg-gray-100 dark:bg-slate-700 overflow-hidden"
        >
          <div
            class="h-full rounded-full bg-primary-500"
            :style="{ width: `${Math.min(100, percent)}%` }"
          />
        </div>
      </div>
      <div class="bg-surface border border-default rounded-lg p-4 shadow-sm">
        <p
          class="text-xs text-content-muted uppercase tracking-wider font-semibold"
        >
          Gốc còn lại
        </p>
        <p class="mt-1 text-xl font-bold text-content">
          {{ formatMoney(current?.principal_remaining ?? 0, false) }} ₫
        </p>
        <p class="text-xs text-content-muted mt-1">
          Lãi kỳ: {{ formatMoney(current?.settlement_interest ?? 0, false) }} ₫
          · Phí: {{ formatMoney(current?.settlement_fee ?? 0, false) }} ₫
        </p>
      </div>
    </div>

    <div
      v-if="fees.length"
      class="mb-6 bg-surface border border-default rounded-lg p-4 shadow-sm"
    >
      <h3 class="text-sm font-semibold text-content mb-2">Bảng phí tất toán</h3>
      <div class="flex flex-wrap gap-2">
        <span
          v-for="rule in fees"
          :key="rule.year"
          class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-app border border-subtle text-content"
        >
          Năm {{ rule.year }}: {{ feeLabel(rule) }}
        </span>
      </div>
    </div>

    <div
      class="bg-surface shadow overflow-hidden sm:rounded-lg border border-default"
    >
      <div class="px-4 py-5 sm:px-6">
        <h3 class="text-lg font-medium text-content">
          Chi tiết theo tháng nếu tất toán
        </h3>
        <p class="mt-1 text-sm text-content-muted">
          Tổng tất toán = dư nợ trước kỳ + lãi kỳ đó + phí theo năm. Progress so
          với số dư ví gom tiền hiện tại. Đã trả {{ months_passed }}/{{
            loan.term_months
          }}
          kỳ · ngày trả {{ payment_day ?? '—' }}.
        </p>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-default">
          <thead class="bg-app">
            <tr>
              <th
                class="px-4 py-3 text-left text-xs font-medium text-content-muted uppercase"
              >
                Tháng
              </th>
              <th
                class="px-4 py-3 text-left text-xs font-medium text-content-muted uppercase"
              >
                Ngày trả
              </th>
              <th
                v-if="showDays"
                class="px-4 py-3 text-left text-xs font-medium text-content-muted uppercase"
              >
                Ngày
              </th>
              <th
                class="px-4 py-3 text-right text-xs font-medium text-content-muted uppercase"
              >
                Kỳ trả
              </th>
              <th
                class="px-4 py-3 text-right text-xs font-medium text-content-muted uppercase"
              >
                Gốc
              </th>
              <th
                class="px-4 py-3 text-right text-xs font-medium text-content-muted uppercase"
              >
                Lãi
              </th>
              <th
                v-if="showPeriodFee"
                class="px-4 py-3 text-right text-xs font-medium text-content-muted uppercase"
              >
                Phí kỳ
              </th>
              <th
                class="px-4 py-3 text-right text-xs font-medium text-content-muted uppercase"
              >
                Dư nợ (trước kỳ)
              </th>
              <th
                class="px-4 py-3 text-right text-xs font-medium text-content-muted uppercase"
              >
                Lãi TT
              </th>
              <th
                class="px-4 py-3 text-right text-xs font-medium text-content-muted uppercase"
              >
                Phí TT
              </th>
              <th
                class="px-4 py-3 text-right text-xs font-medium text-content-muted uppercase"
              >
                Tổng tất toán
              </th>
              <th
                class="px-4 py-3 text-right text-xs font-medium text-content-muted uppercase"
              >
                Progress
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-default">
            <tr
              v-for="row in rows"
              :key="row.month_index"
              :class="rowClass(row)"
            >
              <td class="px-4 py-3 text-sm text-content whitespace-nowrap">
                {{ row.month_index }}
                <span class="text-xs text-content-muted"
                  >(năm {{ row.year }})</span
                >
                <span
                  v-if="row.is_paid"
                  class="ml-1 inline-flex px-1.5 py-0.5 rounded text-[10px] font-medium bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200"
                  >Đã trả</span
                >
                <span
                  v-else-if="row.is_current"
                  class="ml-1 inline-flex px-1.5 py-0.5 rounded text-[10px] font-medium bg-primary-100 dark:bg-primary-900/50 text-primary-800 dark:text-primary-200"
                  >Hiện tại</span
                >
              </td>
              <td class="px-4 py-3 text-sm text-content whitespace-nowrap">
                {{ formatDateVi(row.due_date) }}
              </td>
              <td v-if="showDays" class="px-4 py-3 text-sm text-content-muted">
                {{ row.days ?? '—' }}
              </td>
              <td
                class="px-4 py-3 text-sm text-content text-right whitespace-nowrap"
              >
                {{ formatMoney(row.payment, false) }}
              </td>
              <td
                class="px-4 py-3 text-sm text-content-muted text-right whitespace-nowrap"
              >
                {{ formatMoney(row.principal, false) }}
              </td>
              <td
                class="px-4 py-3 text-sm text-content-muted text-right whitespace-nowrap"
              >
                {{ formatMoney(row.interest, false) }}
              </td>
              <td
                v-if="showPeriodFee"
                class="px-4 py-3 text-sm text-content-muted text-right whitespace-nowrap"
              >
                {{ formatMoney(row.fee, false) }}
              </td>
              <td
                class="px-4 py-3 text-sm text-content text-right whitespace-nowrap font-medium"
              >
                {{ formatMoney(row.principal_before, false) }}
              </td>
              <td
                class="px-4 py-3 text-sm text-content-muted text-right whitespace-nowrap"
              >
                {{ formatMoney(row.settlement_interest, false) }}
              </td>
              <td
                class="px-4 py-3 text-sm text-content-muted text-right whitespace-nowrap"
              >
                {{ formatMoney(row.settlement_fee, false) }}
              </td>
              <td
                class="px-4 py-3 text-sm text-content text-right whitespace-nowrap font-semibold"
              >
                {{ formatMoney(row.settlement_total, false) }}
              </td>
              <td class="px-4 py-3 text-sm text-right whitespace-nowrap">
                <span
                  :class="
                    row.can_settle
                      ? 'text-green-600 dark:text-green-400 font-semibold'
                      : 'text-content'
                  "
                >
                  {{ row.progress_percent }}%
                </span>
                <div
                  class="mt-1 h-1.5 w-16 ml-auto rounded-full bg-gray-100 dark:bg-slate-700 overflow-hidden"
                >
                  <div
                    class="h-full rounded-full"
                    :class="row.can_settle ? 'bg-green-500' : 'bg-primary-500'"
                    :style="{
                      width: `${Math.min(100, row.progress_percent)}%`,
                    }"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p
        v-if="!rows.length"
        class="px-4 py-8 text-sm text-content-muted text-center"
      >
        Không có lịch trả nợ.
      </p>
    </div>
  </AppLayout>
</template>
