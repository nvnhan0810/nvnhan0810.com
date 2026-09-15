<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import { formatMoney, todayVi } from '@/domain';

const props = defineProps({
    wallets: { type: Array, default: () => [] },
    draft: { type: Object, default: null },
});

const confirmOpen = ref(false);
const monthsPaidManual = ref(false);
const receivedTouched = ref(false);

const draft = (props.draft ?? {}) as Record<string, unknown>;

const form = useForm({
    type: (draft.type as string) || 'bank',
    name: (draft.name as string) || '',
    principal_amount: Number(draft.principal_amount ?? 0),
    started_at: (draft.started_at as string) || todayVi(),
    wallet_id: draft.wallet_id != null ? String(draft.wallet_id) : '',
    record_cash_flow: draft.record_cash_flow !== undefined ? Boolean(draft.record_cash_flow) : true,
    received_amount: Number(draft.received_amount ?? draft.principal_amount ?? 0),
    interest_rate: Number(draft.interest_rate ?? 10),
    interest_calculation_method: (draft.interest_calculation_method as string) || 'homecredit',
    term_months: Number(draft.term_months ?? 12),
    months_paid: Number(draft.months_paid ?? 0),
    monthly_payment: Number(draft.monthly_payment ?? 0),
    collection_fee: Number(draft.collection_fee ?? 0),
    payment_day: Number(draft.payment_day ?? 25),
    custom_schedule: [],
});

const customSchedule = ref<Array<{ month_index: number; paid_at: string; principal: number; interest: number }>>([]);

const startsToday = computed(() => {
    const parts = String(form.started_at || '').split('/');
    if (parts.length !== 3) return false;
    const d = new Date(Number(parts[2]), Number(parts[1]) - 1, Number(parts[0]));
    if (Number.isNaN(d.getTime())) return false;
    const now = new Date();
    return d.getFullYear() === now.getFullYear() && d.getMonth() === now.getMonth() && d.getDate() === now.getDate();
});

const walletName = computed(() => {
    const w = props.wallets.find((x: { id: number | string }) => String(x.id) === String(form.wallet_id)) as { name?: string } | undefined;
    return w?.name ?? '';
});

const typeLabel = computed(() => ({ bank: 'Vay ngân hàng', borrow: 'Mượn nợ', lend: 'Cho mượn' }[form.type] || form.type));
const methodLabel = computed(() => ({
    monthly: 'Theo tháng',
    daily: 'Theo ngày (Actual/365)',
    homecredit: 'Home Credit (EMI + Actual/365)',
    custom: 'Tùy chỉnh',
}[form.interest_calculation_method] || form.interest_calculation_method));

const needsSchedulePreview = computed(() =>
    form.type === 'bank'
    && form.interest_calculation_method !== 'custom'
    && Number(form.interest_rate) > 0
    && Number(form.term_months) > 0
    && Number(form.principal_amount) > 0,
);

function calculateMonthly() {
    if (form.type === 'bank' && form.principal_amount > 0 && form.interest_rate > 0 && form.term_months > 0 && form.interest_calculation_method !== 'custom') {
        const r = (form.interest_rate / 100) / 12;
        const n = form.term_months;
        const p = form.principal_amount;
        const m = (p * r * Math.pow(1 + r, n)) / (Math.pow(1 + r, n) - 1);
        form.monthly_payment = Math.round(m);
    }
}

function syncMonthsPaid(force = false) {
    if (monthsPaidManual.value && !force) {
        if (form.term_months > 0 && form.months_paid > form.term_months) {
            form.months_paid = form.term_months;
        }
        return;
    }
    if (force) monthsPaidManual.value = false;
    const parts = String(form.started_at || '').split('/');
    if (parts.length !== 3) {
        form.months_paid = 0;
        return;
    }
    const start = new Date(Number(parts[2]), Number(parts[1]) - 1, Number(parts[0]));
    if (Number.isNaN(start.getTime())) return;
    const now = new Date();
    let diff = (now.getFullYear() - start.getFullYear()) * 12 + (now.getMonth() - start.getMonth());
    if (now.getDate() < start.getDate()) diff -= 1;
    diff = Math.max(0, diff);
    if (form.term_months > 0) diff = Math.min(diff, form.term_months);
    form.months_paid = diff;
}

function addCustomRow() {
    customSchedule.value.push({ month_index: customSchedule.value.length + 1, paid_at: '', principal: 0, interest: 0 });
}

function syncCustomRows(newMonths: number, oldMonths: number) {
    if (!newMonths || newMonths < 1) return;
    if (oldMonths && newMonths < oldMonths) {
        if (!confirm('Giảm số tháng sẽ xóa các dòng cuối. Tiếp tục?')) {
            form.term_months = oldMonths;
            return;
        }
    }
    while (customSchedule.value.length < newMonths) addCustomRow();
    while (customSchedule.value.length > newMonths) customSchedule.value.pop();
    updateCustomDates();
}

function updateCustomDates() {
    const parts = String(form.started_at || '').split('/');
    if (parts.length !== 3) return;
    const base = new Date(Number(parts[2]), Number(parts[1]) - 1, Number(parts[0]));
    if (Number.isNaN(base.getTime())) return;
    customSchedule.value.forEach((row, idx) => {
        const d = new Date(base);
        d.setMonth(d.getMonth() + idx + 1);
        row.paid_at = d.toISOString().split('T')[0] ?? '';
        row.month_index = idx + 1;
    });
}

function computedFee(row: { principal: number; interest: number }) {
    return (form.monthly_payment || 0) - (Number(row.principal || 0) + Number(row.interest || 0));
}

function rowValid(row: { principal: number; interest: number }) {
    const fee = computedFee(row);
    const sum = Number(row.principal || 0) + Number(row.interest || 0) + Number(fee || 0);
    return fee >= 0 && Math.round(sum) === Math.round(form.monthly_payment || 0);
}

watch(() => form.principal_amount, (val) => {
    calculateMonthly();
    if (!receivedTouched.value) form.received_amount = val;
});
watch(() => form.interest_rate, calculateMonthly);
watch(() => form.term_months, (newVal, oldVal) => {
    calculateMonthly();
    syncMonthsPaid();
    if (form.interest_calculation_method === 'custom') syncCustomRows(newVal, oldVal);
});
watch(() => form.started_at, () => {
    syncMonthsPaid(true);
    if (form.interest_calculation_method === 'custom') updateCustomDates();
});
watch(() => form.interest_calculation_method, (newVal) => {
    if (newVal === 'custom') {
        syncCustomRows(form.term_months, customSchedule.value.length);
        updateCustomDates();
    } else {
        calculateMonthly();
    }
});

watch(() => form.type, (type) => {
    if (type !== 'bank') {
        form.interest_calculation_method = 'monthly';
        form.custom_schedule = [];
        customSchedule.value = [];
    } else if (!form.interest_calculation_method) {
        form.interest_calculation_method = 'homecredit';
    }
});

function onSubmit(e: Event) {
    e.preventDefault();
    if (needsSchedulePreview.value) {
        form.post(route('loans.preview'));
        return;
    }
    confirmOpen.value = true;
}

function confirmSubmit() {
    if (form.type === 'bank' && form.interest_calculation_method === 'custom') {
        form.custom_schedule = customSchedule.value.map((row) => ({
            month_index: row.month_index,
            paid_at: row.paid_at,
            principal: row.principal,
            interest: row.interest,
            fee: computedFee(row),
            payment: form.monthly_payment,
        }));
    } else {
        form.custom_schedule = [];
    }
    confirmOpen.value = false;
    form.post(route('loans.store'));
}

if (!draft.monthly_payment) {
    calculateMonthly();
}
syncMonthsPaid(!draft.months_paid);
</script>

<template>
    <Head title="Tạo khoản vay" />
    <AppLayout title="Tạo khoản vay">
        <div class="max-w-4xl mx-auto">
            <div class="md:grid md:grid-cols-3 md:gap-6">
                <div class="md:col-span-1 px-4 sm:px-0">
                    <h3 class="text-lg font-medium leading-6 text-content">Tạo Khoản Vay / Mượn Mới</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-slate-500">Nhập thông tin chi tiết về khoản nợ hoặc cho vay.</p>
                </div>
                <div class="mt-5 md:mt-0 md:col-span-2">
                    <form class="shadow sm:rounded-md sm:overflow-hidden" @submit="onSubmit">
                        <div class="px-4 py-5 bg-surface space-y-6 sm:p-6">
                            <div>
                                <label class="block text-sm font-medium text-content-secondary">Loại khoản nợ</label>
                                <select v-model="form.type" class="mt-1 block w-full py-2 px-3 border border-strong bg-surface text-content rounded-md sm:text-sm">
                                    <option value="bank">Vay Ngân Hàng (Có lãi suất)</option>
                                    <option value="borrow">Mượn Nợ (Cá nhân)</option>
                                    <option value="lend">Cho Mượn (Tài sản)</option>
                                </select>
                            </div>

                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6 sm:col-span-4">
                                    <label class="block text-sm font-medium text-content-secondary">{{ form.type === 'bank' ? 'Tên Ngân hàng / Tổ chức' : 'Tên Người mượn / Cho mượn' }}</label>
                                    <input v-model="form.name" type="text" required class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2 sm:text-sm" />
                                </div>
                                <div class="col-span-6 sm:col-span-3">
                                    <label class="block text-sm font-medium text-content-secondary">Tổng số tiền (Gốc)</label>
                                    <div class="field-money"><MoneyInput v-model="form.principal_amount" required /></div>
                                </div>
                                <div class="col-span-6 sm:col-span-3">
                                    <label class="block text-sm font-medium text-content-secondary">Ngày bắt đầu</label>
                                    <div class="field-date"><DatePicker v-model="form.started_at" required /></div>
                                </div>
                            </div>

                            <div v-if="startsToday" class="rounded-lg border border-primary-100 bg-primary-50/50 dark:bg-primary-900/50 p-4 space-y-3">
                                <p class="text-sm font-medium text-primary-900 dark:text-primary-200">Dòng tiền qua ví (ngày bắt đầu là hôm nay)</p>
                                <p v-if="form.type === 'lend'" class="text-xs text-primary-700 dark:text-primary-300">Cho mượn → chi tiền từ ví.</p>
                                <p v-else class="text-xs text-primary-700 dark:text-primary-300">Vay / mượn → thu tiền vào ví.</p>
                                <label class="flex items-center gap-2 text-sm text-content-secondary">
                                    <input v-model="form.record_cash_flow" type="checkbox" value="1" class="rounded text-primary-600 dark:text-primary-400" />
                                    Ghi nhận giao dịch vào ví khi tạo
                                </label>
                                <div v-if="form.record_cash_flow" class="space-y-3">
                                    <div>
                                        <label class="block text-sm font-medium text-content-secondary">Ví</label>
                                        <select v-model="form.wallet_id" :required="form.record_cash_flow && startsToday" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2 text-sm">
                                            <option value="">Chọn ví</option>
                                            <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.name }}</option>
                                        </select>
                                        <p v-if="!wallets.length" class="mt-1 text-xs text-red-600"><Link :href="route('wallets.create')" class="underline">Tạo ví</Link> trước.</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-content-secondary">{{ form.type === 'lend' ? 'Số tiền thực chi' : 'Số tiền thực nhận vào ví' }}</label>
                                        <div class="field-money">
                                            <MoneyInput v-model="form.received_amount" @update:model-value="receivedTouched = true" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-xs text-content-muted">Ngày bắt đầu không phải hôm nay → chỉ tạo khoản vay, không tạo giao dịch ví.</p>

                            <!-- Bank fields -->
                            <div v-if="form.type === 'bank'" class="border-t border-default pt-4 mt-4 grid grid-cols-6 gap-6">
                                <div class="col-span-6 sm:col-span-2">
                                    <label class="block text-sm font-medium text-content-secondary">Lãi suất (%/năm)</label>
                                    <input v-model.number="form.interest_rate" type="number" step="0.01" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2 sm:text-sm" />
                                </div>
                                <div class="col-span-6 sm:col-span-4">
                                    <label class="block text-sm font-medium text-content-secondary">Phương pháp tính lãi</label>
                                    <select v-model="form.interest_calculation_method" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2 sm:text-sm">
                                        <option value="homecredit">Home Credit (EMI + Actual/365)</option>
                                        <option value="monthly">Lãi suất cố định theo tháng (Đơn giản)</option>
                                        <option value="daily">Tính theo ngày thực tế (Actual/365 - Ngân hàng)</option>
                                        <option value="custom">Tùy chỉnh thủ công từng tháng</option>
                                    </select>
                                </div>
                                <div class="col-span-6 sm:col-span-2">
                                    <label class="block text-sm font-medium text-content-secondary">Thời hạn (Tháng)</label>
                                    <input v-model.number="form.term_months" type="number" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2 sm:text-sm" />
                                </div>
                                <div v-if="form.interest_calculation_method !== 'custom'" class="col-span-6 sm:col-span-2">
                                    <label class="block text-sm font-medium text-content-secondary">Đã đóng (Tháng)</label>
                                    <input v-model.number="form.months_paid" type="number" min="0" :max="form.term_months || undefined" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2 sm:text-sm" @input="monthsPaidManual = true" />
                                </div>
                                <div class="col-span-6 sm:col-span-2">
                                    <label class="block text-sm font-medium text-content-secondary">{{ form.interest_calculation_method !== 'custom' ? 'Đóng hàng tháng (EMI)' : 'Tổng trả hàng tháng (Custom)' }}</label>
                                    <div class="field-money">
                                        <MoneyInput v-model="form.monthly_payment" />
                                    </div>
                                </div>
                                <div class="col-span-6 sm:col-span-2">
                                    <label class="block text-sm font-medium text-content-secondary">Phí thu hộ / kỳ</label>
                                    <div class="field-money">
                                        <MoneyInput v-model="form.collection_fee" />
                                    </div>
                                    <p class="mt-1 text-[11px] text-content-muted">Trừ trước từ EMI, còn lại mới trừ lãi rồi gốc.</p>
                                </div>
                                <div class="col-span-6 sm:col-span-2">
                                    <label class="block text-sm font-medium text-content-secondary">Ngày thanh toán cố định (tháng)</label>
                                    <input v-model.number="form.payment_day" type="number" min="1" max="31" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2 text-sm" />
                                </div>

                                <div v-if="form.interest_calculation_method === 'custom'" class="col-span-6 overflow-x-auto border rounded-lg">
                                    <table class="min-w-full divide-y divide-default text-xs">
                                        <thead class="bg-app">
                                            <tr>
                                                <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-slate-500">Tháng</th>
                                                <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-slate-500">Ngày trả</th>
                                                <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-slate-500">Gốc</th>
                                                <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-slate-500">Lãi</th>
                                                <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-slate-500">Phí (tự tính)</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-subtle bg-surface">
                                            <tr v-for="(row, idx) in customSchedule" :key="idx" :class="rowValid(row) ? '' : 'bg-red-50 dark:bg-red-900/30'">
                                                <td class="px-3 py-2"><input v-model.number="row.month_index" type="number" class="w-16 border-strong bg-surface text-content rounded p-1 text-xs" /></td>
                                                <td class="px-3 py-2"><input v-model="row.paid_at" type="date" class="border-strong bg-surface text-content rounded p-1 text-xs" /></td>
                                                <td class="px-3 py-2"><div class="field-money-sm"><MoneyInput v-model="row.principal" /></div></td>
                                                <td class="px-3 py-2"><div class="field-money-sm"><MoneyInput v-model="row.interest" /></div></td>
                                                <td class="px-3 py-2">
                                                    <span class="block p-1 text-xs">{{ formatMoney(computedFee(row), false) }}</span>
                                                    <p class="text-[10px]" :class="rowValid(row) ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">{{ rowValid(row) ? 'OK' : 'Gốc+Lãi <= Tổng, Phí ≥ 0' }}</p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <p v-if="needsSchedulePreview" class="text-sm text-content-muted border border-dashed border-default rounded-lg p-3">
                                Bước tiếp theo: xem trước lịch trả góp (có thể chỉnh lãi / phí từng kỳ trước khi lưu).
                            </p>
                        </div>
                        <div class="px-4 py-3 bg-app text-right sm:px-6">
                            <button type="submit" class="inline-flex py-2 px-4 rounded-md text-white bg-primary-600 hover:bg-primary-700 text-sm font-medium">
                                {{ needsSchedulePreview ? 'Xem lịch trả góp' : 'Lưu Khoản Nợ' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Confirm modal -->
        <div v-if="confirmOpen" class="fixed z-50 inset-0 overflow-y-auto">
            <div class="flex items-end sm:items-center justify-center min-h-screen p-4">
                <div class="fixed inset-0 bg-gray-500/75" @click="confirmOpen = false" />
                <div class="relative bg-surface rounded-lg shadow-xl w-full max-w-lg">
                    <div class="px-4 pt-5 pb-4 sm:p-6">
                        <h3 class="text-lg font-medium text-content mb-1">Xác nhận tạo khoản vay</h3>
                        <p class="text-sm text-content-muted mb-4">Kiểm tra lại thông số trước khi lưu.</p>
                        <dl class="space-y-2 text-sm border border-subtle rounded-lg divide-y divide-subtle">
                            <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-content-muted">Loại</dt><dd class="font-medium text-right">{{ typeLabel }}</dd></div>
                            <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-content-muted">Tên</dt><dd class="font-medium text-right">{{ form.name || '—' }}</dd></div>
                            <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-content-muted">Số tiền gốc</dt><dd class="font-medium text-right">{{ formatMoney(form.principal_amount) }}</dd></div>
                            <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-content-muted">Ngày bắt đầu</dt><dd class="font-medium text-right">{{ form.started_at || '—' }}</dd></div>
                            <div v-if="form.record_cash_flow && startsToday && walletName" class="flex justify-between gap-4 px-3 py-2"><dt class="text-content-muted">Ví</dt><dd class="font-medium text-right">{{ walletName }}</dd></div>
                            <div v-if="form.record_cash_flow && startsToday" class="flex justify-between gap-4 px-3 py-2"><dt class="text-content-muted">Số tiền vào ví</dt><dd class="font-medium text-right">{{ formatMoney(form.received_amount) }}</dd></div>
                            <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-content-muted">Ghi nhận vào ví</dt><dd class="font-medium text-right">{{ form.record_cash_flow && startsToday ? 'Có' : 'Không' }}</dd></div>
                            <template v-if="form.type === 'bank'">
                                <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-content-muted">Lãi suất</dt><dd class="font-medium text-right">{{ form.interest_rate }}% / năm</dd></div>
                                <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-content-muted">Cách tính lãi</dt><dd class="font-medium text-right">{{ methodLabel }}</dd></div>
                                <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-content-muted">Thời hạn</dt><dd class="font-medium text-right">{{ form.term_months }} tháng</dd></div>
                                <div v-if="form.interest_calculation_method !== 'custom'" class="flex justify-between gap-4 px-3 py-2"><dt class="text-content-muted">Đã đóng</dt><dd class="font-medium text-right">{{ form.months_paid }} tháng</dd></div>
                                <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-content-muted">Trả hàng tháng</dt><dd class="font-medium text-right">{{ formatMoney(form.monthly_payment) }}</dd></div>
                                <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-content-muted">Phí thu hộ</dt><dd class="font-medium text-right">{{ formatMoney(form.collection_fee) }}</dd></div>
                                <div class="flex justify-between gap-4 px-3 py-2"><dt class="text-content-muted">Ngày TT cố định</dt><dd class="font-medium text-right">Ngày {{ form.payment_day }}</dd></div>
                            </template>
                        </dl>
                    </div>
                    <div class="bg-app px-4 py-3 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                        <button type="button" class="px-4 py-2 border border-strong rounded-md text-sm" @click="confirmOpen = false">Quay lại sửa</button>
                        <button type="button" class="px-4 py-2 bg-primary-600 text-white rounded-md text-sm font-medium" @click="confirmSubmit">Xác nhận tạo</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>