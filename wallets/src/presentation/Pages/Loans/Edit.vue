<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, useForm } from '@/application/navigation'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppSelect from '@/Components/AppSelect.vue'
import type { AppSelectOption } from '@/Components/AppSelect.vue'
import { formatMoney } from '@/domain'

type WalletOption = {
  id: number
  name: string
  balance?: number
}

const props = defineProps<{
  loan: {
    id: number
    name: string
    type: string
    wallet_id: number | null
    principal_amount: number
    started_at: string | null
  }
  wallets: WalletOption[]
}>()

const form = useForm({
  name: props.loan.name,
  wallet_id: props.loan.wallet_id ?? ('' as number | string),
})

const walletOptions = computed((): AppSelectOption[] => [
  { value: '', label: '— Không gắn ví (trả thủ công) —' },
  ...props.wallets.map((w) => ({
    value: w.id,
    label:
      w.balance !== undefined
        ? `${w.name} · ${formatMoney(w.balance, false)} ₫`
        : w.name,
  })),
])

const typeLabel = computed(
  () =>
    ({
      bank: 'Vay ngân hàng',
      borrow: 'Mượn nợ',
      lend: 'Cho mượn',
    })[props.loan.type] || props.loan.type,
)

function submit(): void {
  form
    .transform((data) => ({
      ...data,
      wallet_id:
        data.wallet_id === '' || data.wallet_id === null
          ? null
          : Number(data.wallet_id),
    }))
    .put(route('loans.update', props.loan.id))
}
</script>

<template>
  <Head :title="`Sửa: ${loan.name}`" />
  <AppLayout title="Sửa khoản vay">
    <div class="max-w-xl mx-auto">
      <div class="mb-6 flex items-center justify-between gap-4">
        <div>
          <h2 class="text-xl font-bold text-content">Sửa khoản vay</h2>
          <p class="mt-1 text-sm text-content-muted">
            Chỉ đổi tên và gắn ví thanh toán theo lịch. Các thông số vay (gốc,
            lãi, kỳ…) giữ nguyên.
          </p>
        </div>
        <Link
          :href="route('loans.show', loan.id)"
          class="text-sm text-primary-600 dark:text-primary-400 hover:underline whitespace-nowrap"
        >
          &larr; Chi tiết
        </Link>
      </div>

      <div
        class="bg-surface shadow rounded-lg border border-subtle p-4 sm:p-6 mb-4 text-sm text-content-muted space-y-1"
      >
        <p>
          Loại: <span class="text-content font-medium">{{ typeLabel }}</span>
        </p>
        <p>
          Gốc:
          <span class="text-content font-medium">{{
            formatMoney(loan.principal_amount)
          }}</span>
        </p>
        <p v-if="loan.started_at">
          Bắt đầu:
          <span class="text-content font-medium">{{ loan.started_at }}</span>
        </p>
      </div>

      <form
        class="bg-surface shadow rounded-lg border border-subtle p-4 sm:p-6 space-y-5"
        @submit.prevent="submit"
      >
        <div>
          <label class="block text-sm font-medium text-content-secondary"
            >Tên khoản vay</label
          >
          <input
            v-model="form.name"
            type="text"
            required
            maxlength="255"
            class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2 text-sm"
          />
          <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">
            {{ form.errors.name }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium text-content-secondary"
            >Ví thanh toán theo lịch</label
          >
          <AppSelect
            v-model="form.wallet_id"
            class="mt-1"
            :options="walletOptions"
            :searchable="true"
            size="sm"
            search-placeholder="Tìm ví..."
          />
          <p class="mt-2 text-xs text-content-muted">
            Khi gắn ví, mỗi ngày 23:00 cron tự tạo giao dịch trừ ví cho kỳ đến
            hạn chưa trả. Đã có thanh toán/transaction cho kỳ đó thì bỏ qua. Bỏ
            gắn nếu muốn trả thủ công.
          </p>
          <p v-if="form.errors.wallet_id" class="mt-1 text-xs text-red-600">
            {{ form.errors.wallet_id }}
          </p>
        </div>

        <div
          class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2"
        >
          <Link
            :href="route('loans.show', loan.id)"
            class="inline-flex justify-center px-4 py-2 border border-strong rounded-md text-sm text-content"
          >
            Hủy
          </Link>
          <button
            type="submit"
            class="inline-flex justify-center px-4 py-2 rounded-md text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50"
            :disabled="form.processing"
          >
            Lưu
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
