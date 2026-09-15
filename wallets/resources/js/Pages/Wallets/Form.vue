<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import { formatMoney, WALLET_TYPES } from '@/domain';

const props = defineProps({
    wallet: { type: Object, default: null },
    walletTypes: { type: Object, default: () => WALLET_TYPES },
});

const isEdit = computed(() => !!props.wallet);

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
});

const isCreditCard = computed(() => form.type === 'credit_card');

const availableCredit = computed(() =>
    Math.max(0, (Number(form.credit_limit) || 0) - (Number(form.outstanding_balance) || 0)),
);

function submit() {
    if (isEdit.value) {
        form.put(route('wallets.update', props.wallet.id));
    } else {
        form.post(route('wallets.store'));
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Sửa ví' : 'Tạo ví'" />
    <AppLayout :title="isEdit ? 'Sửa ví' : 'Tạo ví'">
        <div class="max-w-xl mx-auto">
            <h2 class="text-2xl font-bold text-content mb-6">{{ isEdit ? 'Sửa ví' : 'Tạo ví mới' }}</h2>

            <form class="bg-surface shadow rounded-lg p-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium text-content-secondary">Tên ví</label>
                    <input v-model="form.name" type="text" required class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2" />
                    <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-content-secondary">Loại ví</label>
                    <select v-model="form.type" required class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2">
                        <option v-for="(label, key) in walletTypes" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>

                <div v-if="isEdit" class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/30 p-4 text-sm">
                    <p class="font-medium text-amber-900 dark:text-amber-200">Số dư không chỉnh tại đây</p>
                    <template v-if="wallet?.is_credit_card">
                        <p class="mt-1 text-amber-800 dark:text-amber-300">
                            Dư nợ: <strong>{{ formatMoney(wallet.outstanding_balance ?? 0) }}</strong>
                            · Hạn mức còn: <strong>{{ formatMoney(wallet.spendable_balance ?? 0) }}</strong>
                        </p>
                    </template>
                    <template v-else>
                        <p class="mt-1 text-amber-800 dark:text-amber-300">Số dư hiện tại: <strong>{{ formatMoney(wallet?.balance ?? 0) }}</strong></p>
                    </template>
                    <p class="mt-2 text-xs text-amber-700 dark:text-amber-400">
                        Dùng giao dịch
                        <Link :href="route('transactions.create', { type: 'adjustment', wallet_id: wallet?.id })" class="underline font-medium">Cân đối</Link>
                        để điều chỉnh số dư.
                    </p>
                </div>

                <!-- Create: non credit card balance -->
                <div v-if="!isEdit && !isCreditCard">
                    <label class="block text-sm font-medium text-content-secondary">Số dư ban đầu (₫)</label>
                    <div class="field-money">
                        <MoneyInput v-model="form.balance" />
                    </div>
                </div>

                <!-- Credit card fields -->
                <div v-if="isCreditCard" class="space-y-4 rounded-lg border border-purple-200 dark:border-purple-800 bg-purple-50/50 dark:bg-purple-900/30 p-4">
                    <p class="text-sm font-medium text-purple-900 dark:text-purple-200">
                        {{ isEdit ? 'Thông tin thẻ (không đổi dư nợ tại đây)' : 'Thông tin thẻ tín dụng' }}
                    </p>
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Hạn mức (₫)</label>
                        <div class="field-money"><MoneyInput v-model="form.credit_limit" required /></div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-content-secondary">Ngày sao kê</label>
                            <input v-model.number="form.statement_day" type="number" min="1" max="31" required class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-content-secondary">Ngày thanh toán</label>
                            <input v-model.number="form.payment_day" type="number" min="1" max="31" required class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2" />
                        </div>
                    </div>
                    <div v-if="!isEdit">
                        <label class="block text-sm font-medium text-content-secondary">Dư nợ ban đầu (₫)</label>
                        <div class="field-money"><MoneyInput v-model="form.outstanding_balance" /></div>
                        <div class="rounded-md bg-surface border border-purple-100 dark:border-purple-800 p-3 text-sm mt-2">
                            <p class="text-gray-600 dark:text-slate-500">Hạn mức còn lại: <span class="font-semibold text-primary-700 dark:text-primary-300">{{ formatMoney(availableCredit) }}</span></p>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-content-secondary">Ghi chú</label>
                    <textarea v-model="form.notes" rows="2" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2" />
                </div>

                <div v-if="isEdit" class="flex items-center gap-2">
                    <input id="is_active" v-model="form.is_active" type="checkbox" class="rounded border-strong bg-surface text-primary-600 dark:text-primary-400" />
                    <label for="is_active" class="text-sm text-content-secondary">Ví đang hoạt động</label>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-4 py-2 bg-primary-600 dark:bg-primary-500 text-white rounded-md text-sm font-medium hover:bg-primary-700 dark:hover:bg-primary-600 disabled:opacity-50" :disabled="form.processing">
                        {{ isEdit ? 'Cập nhật' : 'Tạo ví' }}
                    </button>
                    <Link :href="route('wallets.index')" class="px-4 py-2 border border-strong bg-surface text-content rounded-md text-sm text-content-secondary hover:bg-surface-hover">Hủy</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>