<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import { formatMoney } from '@/domain';

defineProps({
    templates: { type: Array, default: () => [] },
    wallets: { type: Array, default: () => [] },
});

const ADJUSTMENT_DIRECTIONS = { increase: 'Tăng số dư', decrease: 'Giảm số dư' };

const formType = ref('expense');

const form = useForm({
    name: '',
    type: 'expense',
    amount: '',
    description: '',
    category: '',
    default_wallet_id: '',
    adjustment_direction: 'increase',
    from_wallet_id: '',
    to_wallet_id: '',
    fee: 0,
});

function onTypeChange() {
    form.type = formType.value;
}

function submit() {
    form.type = formType.value;
    form.post(route('transaction-templates.store'));
}

function deleteTemplate(t) {
    if (!confirm('Xóa mẫu?')) return;
    router.delete(route('transaction-templates.destroy', t.id));
}
</script>

<template>
    <Head title="Mẫu giao dịch" />
    <AppLayout title="Mẫu giao dịch">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-content">Mẫu giao dịch</h2>
            <p class="mt-1 text-sm text-content-muted">Thu, chi, cân đối số dư hoặc chuyển/rút giữa các ví.</p>
        </div>

        <div class="bg-surface shadow rounded-lg p-6 mb-8">
            <h3 class="text-lg font-medium mb-4">Tạo mẫu</h3>
            <form class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium text-content-secondary">Tên mẫu</label>
                    <input v-model="form.name" type="text" required class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-content-secondary">Loại</label>
                    <select v-model="formType" required class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2" @change="onTypeChange">
                        <option value="expense">Chi</option>
                        <option value="income">Thu</option>
                        <option value="adjustment">Cân đối</option>
                        <option value="transfer">Chuyển / Rút ví</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-content-secondary">Số tiền (₫)</label>
                    <div class="field-money"><MoneyInput v-model="form.amount" required /></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-content-secondary">Mô tả mặc định</label>
                    <input v-model="form.description" type="text" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2" />
                </div>

                <template v-if="formType === 'income' || formType === 'expense'">
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Danh mục</label>
                        <input v-model="form.category" type="text" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Ví mặc định</label>
                        <select v-model="form.default_wallet_id" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
                            <option value="">—</option>
                            <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                </template>

                <template v-if="formType === 'adjustment'">
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Ví</label>
                        <select v-model="form.default_wallet_id" required class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
                            <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Hướng cân đối</label>
                        <select v-model="form.adjustment_direction" required class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
                            <option value="increase">Tăng số dư</option>
                            <option value="decrease">Giảm số dư</option>
                        </select>
                    </div>
                </template>

                <template v-if="formType === 'transfer'">
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Từ ví</label>
                        <select v-model="form.from_wallet_id" required class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
                            <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Đến ví</label>
                        <select v-model="form.to_wallet_id" required class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
                            <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Phí (₫)</label>
                        <div class="field-money"><MoneyInput v-model="form.fee" /></div>
                    </div>
                </template>

                <div class="sm:col-span-2 lg:col-span-3">
                    <button type="submit" class="px-4 py-2 bg-primary-600 dark:bg-primary-500 text-white rounded-md text-sm disabled:opacity-50" :disabled="form.processing">Thêm mẫu</button>
                </div>
            </form>
        </div>

        <div class="bg-surface shadow rounded-lg divide-y divide-subtle">
            <div v-for="t in templates" :key="t.id" class="px-4 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <p class="font-medium text-content">{{ t.name }}</p>
                    <p class="text-sm text-content-muted">
                        {{ t.type_label }} · {{ formatMoney(t.amount, false) }} ₫
                        <template v-if="t.type === 'transfer' && t.fee > 0"> · phí {{ formatMoney(t.fee, false) }} ₫</template>
                        <template v-if="t.description"> · {{ t.description }}</template>
                        <template v-if="t.default_wallet_name"> · Ví: {{ t.default_wallet_name }}</template>
                        <template v-if="t.from_wallet_name && t.to_wallet_name"> · {{ t.from_wallet_name }} → {{ t.to_wallet_name }}</template>
                        <template v-if="t.type === 'adjustment' && t.adjustment_direction"> · {{ ADJUSTMENT_DIRECTIONS[t.adjustment_direction] ?? t.adjustment_direction }}</template>
                    </p>
                </div>
                <div class="flex gap-3 text-sm">
                    <Link :href="route('transactions.create', { template_id: t.id })" class="text-primary-600 dark:text-primary-400 hover:underline">Dùng mẫu</Link>
                    <button type="button" class="text-red-600 dark:text-red-400 hover:underline" @click="deleteTemplate(t)">Xóa</button>
                </div>
            </div>
            <p v-if="!templates.length" class="px-4 py-8 text-center text-content-muted">Chưa có mẫu. Tạo ở đây hoặc lưu từ form giao dịch.</p>
        </div>
    </AppLayout>
</template>