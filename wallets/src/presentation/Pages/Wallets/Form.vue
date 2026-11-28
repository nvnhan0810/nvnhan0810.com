<script setup lang="ts">
import type { PropType } from 'vue'
import type { Wallet } from '@/domain/entities'
import { computed } from 'vue'
import { Head, Link, useForm } from '@/application/navigation'
import AppLayout from '@/Layouts/AppLayout.vue'
import MoneyInput from '@/Components/MoneyInput.vue'
import { formatMoney, WALLET_TYPES } from '@/domain'

const props = defineProps({
  wallet: { type: Object as PropType<Wallet | null>, default: null },
  walletTypes: { type: Object, default: () => WALLET_TYPES },
})

const isEdit = computed(() => !!props.wallet)

const form = useForm({
  name: props.wallet?.name ?? '',
  type: props.wallet?.type ?? 'cash',
  balance: props.wallet ? undefined : 0,
  credit_limit: props.wallet?.credit_limit ?? 0,
  statement_day: props.wallet?.statement_day ?? 1,
  payment_day: props.wallet?.payment_day ?? 1,
  outstanding_balance: props.wallet?.outstanding_balance ?? 0,
  notes: props.wallet?.notes ?? '',
  is_active: props.wallet?.is_active ?? true,
})

const isCreditCard = computed(() => form.type === 'credit_card')

const walletTypeOptions = computed(() =>
  Object.entries(props.walletTypes).map(([key, label]) => ({
    value: key,
    label: String(label),
  })),
)

const availableCredit = computed(() =>
  Math.max(
    0,
    (Number(form.credit_limit) || 0) - (Number(form.outstanding_balance) || 0),
  ),
)

function submit() {
  if (props.wallet) {
    form.put(route('wallets.update', props.wallet.id))
  } else {
    form.post(route('wallets.store'))
  }
}
</script>

<template>
  <Head :title="isEdit ? 'Sửa ví' : 'Tạo ví'" />
  <AppLayout :title="isEdit ? 'Sửa ví' : 'Tạo ví'">
    <div class="max-w-xl mx-auto">
      <Link
        :href="route('wallets.index')"
        class="hidden lg:inline-flex items-center gap-1 mb-2 text-sm text-content-muted hover:text-content"
        >← Ví của bạn</Link
      >
      <h2 class="page-title mb-6">
        {{ isEdit ? 'Sửa ví' : 'Tạo ví mới' }}
      </h2>

      <form class="card p-5 sm:p-6 space-y-5" @submit.prevent="submit">
        <div>
          <label class="form-label">Tên ví</label>
          <input v-model="form.name" type="text" required class="form-input" />
          <p v-if="form.errors.name" class="form-error">
            {{ form.errors.name }}
          </p>
        </div>

        <div>
          <span id="wallet-type-label" class="form-label">Loại ví</span>
          <div
            class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-2"
            role="radiogroup"
            aria-labelledby="wallet-type-label"
          >
            <label
              v-for="opt in walletTypeOptions"
              :key="opt.value"
              class="type-option"
              :class="{ 'type-option-active': form.type === opt.value }"
            >
              <input
                v-model="form.type"
                type="radio"
                name="type"
                :value="opt.value"
                class="sr-only"
              />
              <span class="text-sm font-medium leading-tight">{{
                opt.label
              }}</span>
            </label>
          </div>
          <p v-if="form.errors.type" class="form-error">
            {{ form.errors.type }}
          </p>
        </div>

        <div
          v-if="isEdit"
          class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/30 p-4 text-sm"
        >
          <p class="font-medium text-amber-900 dark:text-amber-200">
            Số dư không chỉnh tại đây
          </p>
          <template v-if="wallet?.is_credit_card">
            <p class="mt-1 text-amber-800 dark:text-amber-300">
              Dư nợ:
              <strong>{{
                formatMoney(wallet.outstanding_balance ?? 0)
              }}</strong>
              · Hạn mức còn:
              <strong>{{ formatMoney(wallet.spendable_balance ?? 0) }}</strong>
            </p>
          </template>
          <template v-else>
            <p class="mt-1 text-amber-800 dark:text-amber-300">
              Số dư hiện tại:
              <strong>{{ formatMoney(wallet?.balance ?? 0) }}</strong>
            </p>
          </template>
          <p class="mt-2 text-xs text-amber-700 dark:text-amber-400">
            Dùng giao dịch
            <Link
              :href="
                route('transactions.create', {
                  type: 'adjustment',
                  wallet_id: wallet?.id,
                })
              "
              class="underline font-medium"
              >Cân đối</Link
            >
            để điều chỉnh số dư.
          </p>
        </div>

        <!-- Create: non credit card balance -->
        <div v-if="!isEdit && !isCreditCard">
          <label class="form-label">Số dư ban đầu (₫)</label>
          <div class="field-money">
            <MoneyInput v-model="form.balance" />
          </div>
        </div>

        <!-- Credit card fields -->
        <div
          v-if="isCreditCard"
          class="space-y-4 rounded-lg border border-purple-200 dark:border-purple-800 bg-purple-50/50 dark:bg-purple-900/30 p-4"
        >
          <p class="text-sm font-medium text-purple-900 dark:text-purple-200">
            {{
              isEdit
                ? 'Thông tin thẻ (không đổi dư nợ tại đây)'
                : 'Thông tin thẻ tín dụng'
            }}
          </p>
          <div>
            <label class="form-label">Hạn mức (₫)</label>
            <div class="field-money">
              <MoneyInput v-model="form.credit_limit" required />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="form-label">Ngày sao kê</label>
              <input
                v-model.number="form.statement_day"
                type="number"
                min="1"
                max="31"
                required
                class="form-input"
              />
            </div>
            <div>
              <label class="form-label">Ngày thanh toán</label>
              <input
                v-model.number="form.payment_day"
                type="number"
                min="1"
                max="31"
                required
                class="form-input"
              />
            </div>
          </div>
          <div v-if="!isEdit">
            <label class="form-label">Dư nợ ban đầu (₫)</label>
            <div class="field-money">
              <MoneyInput v-model="form.outstanding_balance" />
            </div>
            <div
              class="rounded-md bg-surface border border-purple-100 dark:border-purple-800 p-3 text-sm mt-2"
            >
              <p class="text-gray-600 dark:text-slate-500">
                Hạn mức còn lại:
                <span
                  class="font-semibold text-primary-700 dark:text-primary-300"
                  >{{ formatMoney(availableCredit) }}</span
                >
              </p>
            </div>
          </div>
        </div>

        <div>
          <label class="form-label">Ghi chú</label>
          <textarea v-model="form.notes" rows="2" class="form-input" />
        </div>

        <div v-if="isEdit" class="flex items-center gap-2">
          <input
            id="is_active"
            v-model="form.is_active"
            type="checkbox"
            class="w-5 h-5 rounded border-strong accent-primary-600"
          />
          <label for="is_active" class="text-sm text-content-secondary"
            >Ví đang hoạt động</label
          >
        </div>

        <div
          class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2"
        >
          <Link :href="route('wallets.index')" class="btn btn-secondary"
            >Hủy</Link
          >
          <button
            type="submit"
            class="btn btn-primary"
            :disabled="form.processing"
          >
            {{ isEdit ? 'Cập nhật' : 'Tạo ví' }}
          </button>
        </div>
      </form>
    </div>
  </AppLayout>
</template>

<style scoped>
@reference '../../../styles.css';
.type-option {
  @apply flex items-center justify-center text-center min-h-12 px-3 py-2 rounded-xl border border-default bg-surface text-content-secondary cursor-pointer transition-colors hover:bg-surface-hover;
}
.type-option:has(input:focus-visible) {
  @apply ring-2 ring-primary-500 ring-offset-2 ring-offset-surface;
}
.type-option-active {
  @apply border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300 hover:bg-primary-50 dark:hover:bg-primary-900/40;
}
</style>
