<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@/application/navigation'
import { formatMoney } from '@/domain'
import type { DebtGoalProgress, SharedPageProps } from '@/types/inertia'

const page = usePage<SharedPageProps>()
const goal = computed(
  (): DebtGoalProgress | null => page.props.debtGoal ?? null,
)

const barWidth = computed((): string => {
  if (!goal.value) {
    return '0%'
  }
  return `${Math.min(100, Math.max(0, goal.value.percent))}%`
})

const detailHref = computed((): string => {
  if (goal.value?.has_loan) {
    return route('debt-goals.show')
  }
  return route('debt-goals.index')
})
</script>

<template>
  <div v-if="goal" class="mb-6">
    <div
      class="bg-surface rounded-xl border border-default shadow-sm p-4 sm:p-5"
    >
      <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
        <div class="min-w-0">
          <p
            class="text-xs font-semibold uppercase tracking-wider text-content-muted"
          >
            Gom tiền trả nợ
          </p>
          <h3 class="text-base sm:text-lg font-bold text-content truncate">
            {{ goal.name }}
          </h3>
          <p class="text-xs text-content-muted mt-0.5">
            Ví: {{ goal.wallet_name }}
            <template v-if="goal.has_loan && goal.loan_name">
              · {{ goal.loan_name }}</template
            >
          </p>
        </div>
        <div class="text-right shrink-0">
          <p
            class="text-[10px] uppercase tracking-wider text-content-muted font-semibold"
          >
            Tổng tiền
          </p>
          <p class="text-lg sm:text-xl font-bold tracking-tight text-content">
            {{ formatMoney(goal.target_amount, false) }}
            <span class="text-sm font-semibold opacity-80">₫</span>
          </p>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3 mb-3">
        <div>
          <p
            class="text-[10px] uppercase tracking-wider text-content-muted font-semibold"
          >
            Đã gom
          </p>
          <p
            class="text-xl sm:text-2xl font-bold tracking-tight"
            :class="
              goal.reached
                ? 'text-green-600 dark:text-green-400'
                : 'text-primary-600 dark:text-primary-400'
            "
          >
            {{ formatMoney(goal.collected, false) }}
            <span class="text-sm font-semibold opacity-80">₫</span>
          </p>
        </div>
        <div class="text-right">
          <p
            class="text-[10px] uppercase tracking-wider text-content-muted font-semibold"
          >
            Tiến độ
          </p>
          <p
            class="text-xl sm:text-2xl font-bold tracking-tight"
            :class="
              goal.reached
                ? 'text-green-600 dark:text-green-400'
                : 'text-content'
            "
          >
            {{ goal.percent }}%
          </p>
        </div>
      </div>

      <div
        class="h-2.5 rounded-full bg-gray-100 dark:bg-slate-700 overflow-hidden"
      >
        <div
          class="h-full rounded-full transition-all duration-500"
          :class="
            goal.reached
              ? 'bg-green-500 dark:bg-green-400'
              : 'bg-primary-500 dark:bg-primary-400'
          "
          :style="{ width: barWidth }"
        />
      </div>

      <div
        class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm"
      >
        <p v-if="goal.has_loan" class="text-xs text-content-muted">
          Gốc {{ formatMoney(goal.principal_remaining ?? 0, false) }} ₫ + lãi
          {{ formatMoney(goal.settlement_interest ?? 0, false) }} ₫ + phí
          {{ formatMoney(goal.settlement_fee ?? 0, false) }} ₫
          <template v-if="goal.current_year"
            >(năm {{ goal.current_year }})</template
          >
        </p>
        <p v-else-if="!goal.reached" class="text-content-muted text-xs">
          Còn {{ formatMoney(goal.remaining, false) }} ₫
        </p>
        <span
          v-else
          class="text-xs font-semibold text-green-600 dark:text-green-400"
          >Đã đạt mục tiêu</span
        >

        <Link
          :href="detailHref"
          class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline ml-auto"
        >
          Chi tiết →
        </Link>
      </div>
    </div>
  </div>
</template>
