<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import { formatMoney, todayVi } from '@/domain';

const props = defineProps({
    wallets: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    defaultDate: { type: String, default: '' },
    defaultWalletId: { type: [Number, String], default: null },
});

let rowKey = 1;

function emptyRow(overrides = {}) {
    return {
        key: rowKey++,
        type: 'expense',
        wallet_id: props.defaultWalletId ?? props.wallets[0]?.id ?? '',
        from_wallet_id: '',
        to_wallet_id: '',
        amount: '',
        fee: 0,
        description: '',
        category: '',
        note: '',
        transaction_template_id: '',
        ...overrides,
    };
}

const defaultWalletId = ref(props.defaultWalletId ?? props.wallets[0]?.id ?? '');

const form = useForm({
    transacted_at: props.defaultDate || todayVi(),
    items: [emptyRow(), emptyRow(), emptyRow()],
});

const totals = computed(() => {
    let income = 0;
    let expense = 0;
    let transfer = 0;
    for (const row of form.items) {
        const amount = Number(row.amount) || 0;
        if (row.type === 'income') income += amount;
        else if (row.type === 'expense') expense += amount;
        else if (row.type === 'transfer') transfer += amount;
    }
    return { income, expense, transfer, net: income - expense, count: form.items.length };
});

function addRow() {
    form.items.push(emptyRow({ wallet_id: defaultWalletId.value || props.wallets[0]?.id || '' }));
}

function removeRow(index) {
    if (form.items.length <= 1) {
        form.items.splice(0, 1, emptyRow({ wallet_id: defaultWalletId.value || '' }));
        return;
    }
    form.items.splice(index, 1);
}

function applyDefaultWalletToEmpty() {
    form.items.forEach((row) => {
        if (row.type !== 'transfer' && !row.wallet_id) {
            row.wallet_id = defaultWalletId.value;
        }
    });
}

function applyTemplate(index, templateId) {
    const row = form.items[index];
    if (!templateId) {
        row.transaction_template_id = '';
        return;
    }
    const t = props.templates.find((x) => String(x.id) === String(templateId));
    if (!t) return;
    row.transaction_template_id = t.id;
    row.type = t.type;
    row.amount = t.amount;
    row.fee = t.fee || 0;
    row.description = t.description || t.name;
    row.category = t.category || '';
    if (t.type === 'transfer') {
        if (t.from_wallet_id) row.from_wallet_id = t.from_wallet_id;
        if (t.to_wallet_id) row.to_wallet_id = t.to_wallet_id;
    } else if (t.default_wallet_id) {
        row.wallet_id = t.default_wallet_id;
    }
}

function fillFromTemplateQuick(templateId) {
    if (!templateId) return;
    const emptyIndex = form.items.findIndex((r) => !r.description && !r.amount);
    if (emptyIndex >= 0) {
        applyTemplate(emptyIndex, templateId);
        return;
    }
    form.items.push(emptyRow());
    applyTemplate(form.items.length - 1, templateId);
}

function submit() {
    const payload = {
        transacted_at: form.transacted_at,
        items: form.items
            .filter((r) => r.description || r.amount)
            .map((r) => ({
                type: r.type,
                description: r.description,
                amount: r.amount,
                category: r.category || null,
                note: r.note || null,
                wallet_id: r.type === 'transfer' ? null : r.wallet_id,
                from_wallet_id: r.type === 'transfer' ? r.from_wallet_id : null,
                to_wallet_id: r.type === 'transfer' ? r.to_wallet_id : null,
                fee: r.type === 'transfer' ? (r.fee || 0) : null,
                transaction_template_id: r.transaction_template_id || null,
            })),
    };

    if (!payload.items.length) {
        form.setError('items', 'Thêm ít nhất một giao dịch.');
        return;
    }

    form.transform(() => payload).post(route('transactions.bulk.store'), {
        preserveScroll: true,
    });
}

function fieldError(index, field) {
    return form.errors[`items.${index}.${field}`] || form.errors[`items.${index}`];
}
</script>

<template>
    <Head title="Ghi giao dịch theo ngày" />
    <AppLayout title="Ghi giao dịch theo ngày">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-2xl font-bold text-content">Ghi giao dịch theo ngày</h2>
                <p class="mt-1 text-sm text-content-muted">Nhập nhiều giao dịch cùng một ngày — phù hợp ghi sổ cả ngày.</p>
            </div>
            <div class="flex gap-2">
                <Link :href="route('transactions.create')" class="px-3 py-2 text-sm rounded-md border border-strong bg-surface text-content-secondary hover:bg-surface-hover">Một giao dịch</Link>
                <Link :href="route('transactions.index')" class="px-3 py-2 text-sm rounded-md border border-strong bg-surface text-content-secondary hover:bg-surface-hover">Danh sách</Link>
            </div>
        </div>

        <div class="bg-surface rounded-2xl border border-subtle shadow-sm p-4 sm:p-6 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-content-secondary mb-1">Ngày giao dịch</label>
                    <DatePicker v-model="form.transacted_at" required class="mt-0 block w-full rounded-md border border-strong bg-surface text-content p-2" />
                    <p v-if="form.errors.transacted_at" class="mt-1 text-xs text-red-600">{{ form.errors.transacted_at }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-content-secondary mb-1">Ví mặc định (thu/chi)</label>
                    <select v-model="defaultWalletId" class="block w-full rounded-md border border-strong bg-surface text-content p-2" @change="applyDefaultWalletToEmpty">
                        <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-content-secondary mb-1">Thêm từ mẫu</label>
                    <select class="block w-full rounded-md border border-strong bg-surface text-content p-2" @change="fillFromTemplateQuick($event.target.value); $event.target.value = ''">
                        <option value="">— Chọn mẫu —</option>
                        <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }} ({{ t.type_label }})</option>
                    </select>
                </div>
            </div>

            <p v-if="form.errors.items" class="text-sm text-red-600">{{ form.errors.items }}</p>

            <div class="space-y-3">
                <div
                    v-for="(row, index) in form.items"
                    :key="row.key"
                    class="rounded-xl border border-subtle bg-app/40 p-3 sm:p-4 space-y-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-content-muted uppercase tracking-wide">#{{ index + 1 }}</p>
                        <button type="button" class="text-xs text-red-500 hover:underline" @click="removeRow(index)">Xóa dòng</button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-content-muted mb-1">Loại</label>
                            <select v-model="row.type" class="w-full rounded-md border border-strong bg-surface text-content p-2 text-sm">
                                <option value="expense">Chi</option>
                                <option value="income">Thu</option>
                                <option value="transfer">Chuyển ví</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-content-muted mb-1">Số tiền</label>
                            <MoneyInput v-model="row.amount" class="w-full rounded-md border border-strong bg-surface text-content p-2 text-sm" />
                            <p v-if="fieldError(index, 'amount')" class="mt-1 text-xs text-red-600">{{ fieldError(index, 'amount') }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-content-muted mb-1">Mô tả</label>
                            <input v-model="row.description" type="text" class="w-full rounded-md border border-strong bg-surface text-content p-2 text-sm" placeholder="VD: Ăn trưa, xăng xe…">
                            <p v-if="fieldError(index, 'description')" class="mt-1 text-xs text-red-600">{{ fieldError(index, 'description') }}</p>
                        </div>
                    </div>

                    <div v-if="row.type !== 'transfer'" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-content-muted mb-1">Ví</label>
                            <select v-model="row.wallet_id" class="w-full rounded-md border border-strong bg-surface text-content p-2 text-sm">
                                <option value="">Chọn ví</option>
                                <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                            <p v-if="fieldError(index, 'wallet_id')" class="mt-1 text-xs text-red-600">{{ fieldError(index, 'wallet_id') }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-content-muted mb-1">Danh mục</label>
                            <input v-model="row.category" type="text" class="w-full rounded-md border border-strong bg-surface text-content p-2 text-sm" placeholder="Tùy chọn">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-content-muted mb-1">Ghi chú</label>
                            <input v-model="row.note" type="text" class="w-full rounded-md border border-strong bg-surface text-content p-2 text-sm" placeholder="Tùy chọn">
                        </div>
                    </div>

                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-content-muted mb-1">Từ ví</label>
                            <select v-model="row.from_wallet_id" class="w-full rounded-md border border-strong bg-surface text-content p-2 text-sm">
                                <option value="">Chọn ví</option>
                                <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                            <p v-if="fieldError(index, 'from_wallet_id')" class="mt-1 text-xs text-red-600">{{ fieldError(index, 'from_wallet_id') }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-content-muted mb-1">Đến ví</label>
                            <select v-model="row.to_wallet_id" class="w-full rounded-md border border-strong bg-surface text-content p-2 text-sm">
                                <option value="">Chọn ví</option>
                                <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.name }}</option>
                            </select>
                            <p v-if="fieldError(index, 'to_wallet_id')" class="mt-1 text-xs text-red-600">{{ fieldError(index, 'to_wallet_id') }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-content-muted mb-1">Phí</label>
                            <MoneyInput v-model="row.fee" class="w-full rounded-md border border-strong bg-surface text-content p-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-content-muted mb-1">Ghi chú</label>
                            <input v-model="row.note" type="text" class="w-full rounded-md border border-strong bg-surface text-content p-2 text-sm" placeholder="Tùy chọn">
                        </div>
                    </div>

                    <div v-if="templates.length" class="flex items-center gap-2">
                        <label class="text-xs text-content-muted shrink-0">Mẫu:</label>
                        <select
                            class="flex-1 rounded-md border border-strong bg-surface text-content p-1.5 text-xs"
                            :value="row.transaction_template_id || ''"
                            @change="applyTemplate(index, $event.target.value)"
                        >
                            <option value="">—</option>
                            <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-2 border-t border-subtle">
                <button type="button" class="inline-flex items-center justify-center px-3 py-2 text-sm rounded-md border border-dashed border-strong text-content-secondary hover:bg-surface-hover" @click="addRow">
                    + Thêm dòng
                </button>
                <div class="text-sm text-content-muted space-y-0.5 sm:text-right">
                    <p>{{ totals.count }} dòng · Thu {{ formatMoney(totals.income) }} · Chi {{ formatMoney(totals.expense) }}</p>
                    <p class="font-medium text-content">Net: {{ formatMoney(totals.net) }} <span v-if="totals.transfer">· Chuyển {{ formatMoney(totals.transfer) }}</span></p>
                </div>
            </div>

            <div class="flex gap-3 pt-1">
                <button
                    type="button"
                    class="px-4 py-2 bg-primary-600 dark:bg-primary-500 text-white rounded-md text-sm font-medium hover:bg-primary-700 disabled:opacity-50"
                    :disabled="form.processing"
                    @click="submit"
                >
                    {{ form.processing ? 'Đang lưu…' : 'Lưu tất cả' }}
                </button>
                <Link :href="route('transactions.index')" class="px-4 py-2 border border-strong bg-surface text-content rounded-md text-sm text-content-secondary hover:bg-surface-hover">Hủy</Link>
            </div>
        </div>
    </AppLayout>
</template>
