<script setup lang="ts">
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import { formatMoney, todayVi } from '@/domain';

const props = defineProps({
    wallets: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    selectedTemplateId: { type: [Number, String], default: null },
    prefill: { type: Object, default: () => ({}) },
    walletBalances: { type: Object, default: () => ({}) },
});

const defaultWalletId = props.prefill.wallet_id
    ?? props.templates.find((t) => t.id === props.selectedTemplateId)?.default_wallet_id
    ?? props.wallets[0]?.id
    ?? '';

const form = useForm({
    transaction_template_id: props.selectedTemplateId ?? '',
    wallet_id: defaultWalletId,
    from_wallet_id: '',
    to_wallet_id: '',
    type: props.prefill.type ?? 'expense',
    amount: props.prefill.amount ?? '',
    target_balance: '',
    fee: 0,
    description: props.prefill.description ?? '',
    category: props.prefill.category ?? '',
    transacted_at: props.prefill.transacted_at ?? todayVi(),
    note: '',
    recurring_item_id: props.prefill.recurring_item_id ?? '',
    recurring_occurrence_id: props.prefill.recurring_occurrence_id ?? '',
    save_as_template: false,
    template_name: '',
});

const fromTemplate = computed(() => !!form.transaction_template_id);
const selectedTemplateSelect = computed({
    get: () => form.transaction_template_id ? String(form.transaction_template_id) : '',
    set: (v) => applyTemplate(v),
});

const currentBalance = computed(() => {
    const bal = props.walletBalances[String(form.wallet_id)];
    return bal == null ? 0 : Number(bal);
});

const adjustmentDelta = computed(() => {
    if (form.target_balance === '' || form.target_balance === null) return null;
    return Math.round(Number(form.target_balance) - currentBalance.value);
});

const adjustmentPreview = computed(() => {
    const delta = adjustmentDelta.value;
    if (delta === null || !Number.isFinite(delta) || delta === 0) return '';
    const sign = delta > 0 ? '+' : '−';
    return sign + formatMoney(Math.abs(delta));
});

const transferTotal = computed(() => (Number(form.amount) || 0) + (Number(form.fee) || 0));

function applyTemplate(id) {
    if (!id) {
        form.transaction_template_id = '';
        return;
    }
    const t = props.templates.find((x) => String(x.id) === String(id));
    if (!t) return;
    form.transaction_template_id = t.id;
    form.type = t.type;
    form.amount = t.amount;
    form.fee = t.fee || 0;
    form.description = t.description || t.name;
    form.category = t.category || '';
    if (t.default_wallet_id) form.wallet_id = t.default_wallet_id;
    if (t.from_wallet_id) form.from_wallet_id = t.from_wallet_id;
    if (t.to_wallet_id) form.to_wallet_id = t.to_wallet_id;
    if (t.type === 'adjustment') {
        const signed = (t.adjustment_direction === 'decrease' ? -1 : 1) * Number(t.amount || 0);
        form.target_balance = currentBalance.value + signed;
    }
}

if (props.selectedTemplateId) {
    applyTemplate(props.selectedTemplateId);
}

watch(() => form.wallet_id, () => {
    if (form.type === 'adjustment' && form.transaction_template_id) {
        const t = props.templates.find((x) => x.id === form.transaction_template_id);
        if (t?.type === 'adjustment') {
            const signed = (t.adjustment_direction === 'decrease' ? -1 : 1) * Number(t.amount || 0);
            form.target_balance = currentBalance.value + signed;
        }
    }
});

function submit() {
    form.post(route('transactions.store'));
}
</script>

<template>
    <Head title="Ghi giao dịch" />
    <AppLayout title="Ghi giao dịch">
        <div class="max-w-2xl mx-auto">
            <div class="mb-6 flex items-center justify-between gap-3">
                <h2 class="text-2xl font-bold text-content">Ghi giao dịch</h2>
                <Link :href="route('transactions.bulk')" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">Ghi cả ngày →</Link>
            </div>

            <form class="bg-surface shadow rounded-lg p-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium text-content-secondary">Chọn mẫu (tùy chọn)</label>
                    <select v-model="selectedTemplateSelect" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2">
                        <option value="">— Không dùng mẫu —</option>
                        <option v-for="t in templates" :key="t.id" :value="String(t.id)">{{ t.name }} ({{ t.type_label }})</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-content-secondary">Loại giao dịch</label>
                    <select v-model="form.type" required class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2">
                        <option value="expense">Chi</option>
                        <option value="income">Thu</option>
                        <option value="adjustment">Cân đối</option>
                        <option value="transfer">Chuyển / Rút ví</option>
                    </select>
                </div>

                <!-- Thu / Chi -->
                <div v-if="form.type === 'income' || form.type === 'expense'" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-content-secondary">Ví</label>
                            <select v-model="form.wallet_id" required class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2">
                                <option value="">Chọn ví</option>
                                <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-content-secondary">Số tiền (₫)</label>
                            <div class="field-money"><MoneyInput v-model="form.amount" required /></div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Mô tả</label>
                        <input v-model="form.description" type="text" required class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Danh mục</label>
                        <input v-model="form.category" type="text" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2" />
                    </div>
                </div>

                <!-- Cân đối -->
                <div v-if="form.type === 'adjustment'" class="space-y-4 rounded-lg border border-amber-200 bg-amber-50/30 dark:bg-amber-900/20 p-4">
                    <p class="text-xs text-amber-800 dark:text-amber-200">Nhập số dư cuối cùng cần khớp. Hệ thống tự tính mức tăng/giảm so với số dư hiện tại.</p>
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Ví</label>
                        <select v-model="form.wallet_id" required class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2">
                            <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.name }} ({{ formatMoney(w.balance) }})</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-content-secondary">Số dư hiện tại</label>
                            <p class="mt-1 block w-full rounded-md border border-subtle bg-app text-content p-2 text-sm font-semibold">{{ formatMoney(currentBalance) }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-content-secondary">Số dư cuối cùng (₫)</label>
                            <div class="field-money"><MoneyInput v-model="form.target_balance" required /></div>
                            <p v-if="form.errors.target_balance" class="mt-1 text-xs text-red-600">{{ form.errors.target_balance }}</p>
                        </div>
                    </div>
                    <p v-if="adjustmentPreview" class="text-xs text-amber-900 dark:text-amber-200">
                        Điều chỉnh: <span class="font-semibold">{{ adjustmentPreview }}</span>
                    </p>
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Lý do</label>
                        <input v-model="form.description" type="text" required placeholder="VD: Đối chiếu sao kê tháng 5" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2" />
                    </div>
                </div>

                <!-- Chuyển ví -->
                <div v-if="form.type === 'transfer'" class="space-y-4 rounded-lg border border-blue-200 bg-blue-50/30 dark:bg-blue-900/20 p-4">
                    <p class="text-xs text-blue-800 dark:text-blue-200">Rút/chuyển từ ví nguồn sang ví đích. Phí trừ thêm ở ví nguồn.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-content-secondary">Từ ví</label>
                            <select v-model="form.from_wallet_id" required class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2">
                                <option value="">Chọn ví nguồn</option>
                                <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-content-secondary">Đến ví</label>
                            <select v-model="form.to_wallet_id" required class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2">
                                <option value="">Chọn ví đích</option>
                                <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-content-secondary">Số tiền chuyển (₫)</label>
                            <div class="field-money"><MoneyInput v-model="form.amount" required /></div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-content-secondary">Phí (₫)</label>
                            <div class="field-money"><MoneyInput v-model="form.fee" /></div>
                        </div>
                    </div>
                    <p class="text-xs text-gray-600 dark:text-slate-500">Ví nguồn trừ: <span class="font-semibold">{{ formatMoney(transferTotal) }}</span></p>
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Mô tả</label>
                        <input v-model="form.description" type="text" required placeholder="VD: Chuyển tiền mặt sang ngân hàng" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2" />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-content-secondary">Ngày giao dịch</label>
                    <div class="field-date"><DatePicker v-model="form.transacted_at" required /></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-content-secondary">Ghi chú</label>
                    <textarea v-model="form.note" rows="2" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2" />
                </div>

                <div v-if="!fromTemplate" class="rounded-md bg-app p-4 border border-default">
                    <label class="flex items-center gap-2 text-sm text-content-secondary">
                        <input v-model="form.save_as_template" type="checkbox" value="1" class="rounded border-strong bg-surface text-primary-600 dark:text-primary-400" />
                        Lưu thành mẫu
                    </label>
                    <div v-if="form.save_as_template" class="mt-3">
                        <input v-model="form.template_name" type="text" placeholder="Tên mẫu" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2 text-sm" />
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-4 py-2 bg-primary-600 dark:bg-primary-500 text-white rounded-md text-sm font-medium hover:bg-primary-700 dark:hover:bg-primary-600 disabled:opacity-50" :disabled="form.processing">Lưu</button>
                    <Link :href="route('transactions.index')" class="px-4 py-2 border border-strong bg-surface text-content rounded-md text-sm text-content-secondary hover:bg-surface-hover">Hủy</Link>
                </div>
            </form>
        </div>
    </AppLayout>
</template>