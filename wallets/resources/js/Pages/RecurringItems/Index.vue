<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import { diffEditFormIds } from '@/domain/reporting/edit-form-sync';

type RecurringItemRow = {
    id: number;
    name: string;
    type: string;
    amount: number;
    day_of_month: number;
    effective_from: string | null;
    ends_at: string | null;
    note: string | null;
    is_active: boolean;
    next_due: string | null;
    days_until: number | null;
};

type EditForm = ReturnType<typeof useForm<{
    name: string;
    type: string;
    amount: number | string;
    day_of_month: number;
    effective_from: string;
    ends_at: string;
    note: string;
    is_active: boolean;
}>>;

const props = defineProps<{
    items: RecurringItemRow[];
}>();

const createForm = useForm({
    name: '',
    type: 'expense',
    amount: '',
    day_of_month: 1,
    effective_from: '',
    ends_at: '',
    note: '',
});

function makeEditForm(item: RecurringItemRow): EditForm {
    return useForm({
        name: item.name,
        type: item.type,
        amount: item.amount,
        day_of_month: item.day_of_month,
        effective_from: item.effective_from ?? '',
        ends_at: item.ends_at ?? '',
        note: item.note ?? '',
        is_active: item.is_active,
    });
}

const editForms = reactive<Record<string, EditForm>>({});

function syncEditForms(items: RecurringItemRow[]): void {
    const { toAdd, toRemove } = diffEditFormIds(
        Object.keys(editForms),
        items.map((item) => item.id),
    );

    for (const id of toRemove) {
        delete editForms[String(id)];
    }

    for (const id of toAdd) {
        const item = items.find((row) => String(row.id) === String(id));
        if (item) {
            editForms[String(item.id)] = makeEditForm(item);
        }
    }
}

watch(() => props.items, (items) => syncEditForms(items ?? []), { immediate: true, deep: true });

const rows = computed(() =>
    (props.items ?? []).flatMap((item) => {
        const form = editForms[String(item.id)];
        return form ? [{ item, form }] : [];
    }),
);

function submitCreate(): void {
    createForm.post(route('recurring-items.store'), {
        onSuccess: () => createForm.reset('name', 'amount', 'note', 'effective_from', 'ends_at'),
    });
}

function submitUpdate(item: RecurringItemRow, form: EditForm): void {
    form.put(route('recurring-items.update', item.id));
}

function deleteItem(item: RecurringItemRow): void {
    if (!confirm('Xóa khoản này?')) return;
    router.delete(route('recurring-items.destroy', item.id));
}

function formatNextDue(d: string | null): string {
    if (!d) return '—';
    const s = String(d).slice(0, 10);
    const [y, m, day] = s.split('-');
    return y && m && day ? `${day}/${m}/${y}` : d;
}
</script>

<template>
    <Head title="Thu chi cố định" />
    <AppLayout title="Thu chi cố định">
        <div class="mb-6">
            <h2 class="text-2xl font-bold leading-7 text-content sm:text-3xl">Kế hoạch tài chính</h2>
        </div>

        <div class="flex flex-wrap border-b border-subtle mb-6">
            <Link :href="route('loans.index')" class="px-4 py-2 border-b-2 border-transparent text-content-muted hover:text-content font-medium text-sm">Khoản vay & Nợ</Link>
            <Link :href="route('recurring-items.index')" class="px-4 py-2 border-b-2 border-primary-600 dark:border-primary-400 text-primary-600 dark:text-primary-400 font-semibold text-sm">Thu chi cố định</Link>
            <Link :href="route('fixed-expenses.index')" class="px-4 py-2 border-b-2 border-transparent text-content-muted hover:text-content font-medium text-sm">Tổng hợp chi cố định</Link>
        </div>

        <p class="mb-6 text-sm text-content-muted">Đặt ngày trong tháng và khoảng hiệu lực. Nhắc sẽ hiện trên tổng quan khi gần đến hạn; chọn ví lúc ghi giao dịch thực tế.</p>

        <div class="bg-surface shadow rounded-lg p-6 mb-8">
            <h3 class="text-lg font-medium text-content mb-4">Thêm khoản mới</h3>
            <form class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4" @submit.prevent="submitCreate">
                <div>
                    <label class="block text-sm font-medium text-content-secondary">Tên</label>
                    <input v-model="createForm.name" type="text" required class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-content-secondary">Loại</label>
                    <select v-model="createForm.type" required class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
                        <option value="expense">Chi cố định</option>
                        <option value="income">Thu cố định</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-content-secondary">Số tiền (₫)</label>
                    <div class="field-money"><MoneyInput v-model="createForm.amount" required /></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-content-secondary">Ngày trong tháng</label>
                    <input v-model.number="createForm.day_of_month" type="number" min="1" max="31" required class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-content-secondary">Bắt đầu từ <span class="text-gray-400">(tùy chọn)</span></label>
                    <input v-model="createForm.effective_from" type="date" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-content-secondary">Kết thúc <span class="text-gray-400">(tùy chọn)</span></label>
                    <input v-model="createForm.ends_at" type="date" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-content-secondary">Ghi chú</label>
                    <input v-model="createForm.note" type="text" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2" />
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <button type="submit" class="px-4 py-2 bg-primary-600 dark:bg-primary-500 text-white rounded-md text-sm font-medium disabled:opacity-50" :disabled="createForm.processing">Thêm</button>
                </div>
            </form>
        </div>

        <div class="space-y-4">
            <div
                v-for="row in rows"
                :key="row.item.id"
                class="bg-surface rounded-lg shadow border border-default p-5"
            >
                <form class="grid grid-cols-1 lg:grid-cols-5 gap-3 items-end" @submit.prevent="submitUpdate(row.item, row.form)">
                    <div class="lg:col-span-2">
                        <label class="text-xs text-content-muted">Tên</label>
                        <input v-model="row.form.name" type="text" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-content-muted">Loại</label>
                        <select v-model="row.form.type" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2 text-sm">
                            <option value="expense">Chi</option>
                            <option value="income">Thu</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-content-muted">Số tiền</label>
                        <div class="field-money"><MoneyInput v-model="row.form.amount" /></div>
                    </div>
                    <div>
                        <label class="text-xs text-content-muted">Ngày</label>
                        <input v-model.number="row.form.day_of_month" type="number" min="1" max="31" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-content-muted">Bắt đầu</label>
                        <input v-model="row.form.effective_from" type="date" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2 text-sm" />
                    </div>
                    <div>
                        <label class="text-xs text-content-muted">Kết thúc</label>
                        <input v-model="row.form.ends_at" type="date" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2 text-sm" />
                    </div>
                    <div class="flex flex-wrap gap-2 items-center">
                        <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-slate-500">
                            <input v-model="row.form.is_active" type="checkbox" class="rounded text-primary-600 dark:text-primary-400" /> Bật
                        </label>
                        <button type="submit" class="px-3 py-1.5 bg-primary-600 dark:bg-primary-500 text-white text-xs rounded-md" :disabled="row.form.processing">Lưu</button>
                    </div>
                </form>
                <p class="mt-2 text-xs text-content-muted">
                    Kỳ tới: {{ formatNextDue(row.item.next_due) }} (còn {{ row.item.days_until }} ngày)
                </p>
                <button type="button" class="mt-2 text-xs text-red-600 dark:text-red-400 hover:underline" @click="deleteItem(row.item)">Xóa</button>
            </div>
            <p v-if="!items.length" class="text-center text-content-muted py-8">Chưa có khoản thu/chi cố định.</p>
        </div>
    </AppLayout>
</template>
