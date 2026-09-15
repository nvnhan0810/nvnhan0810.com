<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatMoney, formatDateVi } from '@/domain';

const props = defineProps({
    loan: { type: Object, required: true },
    timeline: { type: Array, default: () => [] },
    schedule: { type: Array, default: () => [] },
    monthsPassed: { type: Number, default: 0 },
    paymentDay: { type: [Number, String], default: null },
});

const method = computed(() => props.loan.interest_calculation_method ?? 'monthly');

function loanTypeName() {
    if (props.loan.type === 'bank') return 'Vay Ngân hàng';
    if (props.loan.type === 'borrow') return 'Mượn Nợ';
    return 'Cho Mượn';
}

function methodBadge() {
    if (method.value === 'homecredit') return { label: 'Home Credit EMI', class: 'bg-violet-100 dark:bg-violet-900/50 text-violet-800 dark:text-violet-200' };
    if (method.value === 'daily') return { label: 'Actual/365', class: 'bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200' };
    if (method.value === 'custom') return { label: 'Custom (nhập tay)', class: 'bg-purple-100 dark:bg-purple-900/50 text-purple-800 dark:text-purple-200' };
    return { label: 'Theo tháng cố định', class: 'bg-muted text-content-secondary' };
}

function fmtDate(val: unknown): string {
    if (!val) return '—';
    const formatted = formatDateVi(val);
    return formatted || '—';
}

function scheduleDateLabel(row: { period_due_date?: unknown; period?: { date?: unknown } }): string | null {
    const due = fmtDate(row.period_due_date);
    const scheduled = fmtDate(row.period?.date);
    if (!scheduled || scheduled === '—' || scheduled === due) {
        return null;
    }
    return scheduled;
}

function paymentDate(p) {
    return p.paid_at ?? p.paid_at_label ?? '';
}

const sortedPayments = computed(() =>
    [...(props.loan.payments ?? [])].sort((a, b) => String(b.paid_at).localeCompare(String(a.paid_at))),
);
</script>

<template>
    <Head :title="`Chi tiết: ${loan.name}`" />
    <AppLayout :title="loan.name">
        <div class="bg-surface shadow overflow-hidden sm:rounded-lg mb-6">
            <div class="px-4 py-5 sm:px-6 flex justify-between items-center">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-content">Chi tiết khoản vay: {{ loan.name }}</h3>
                    <p class="mt-1 text-sm text-content-muted">Thông tin chi tiết và lịch trả nợ.</p>
                </div>
                <Link :href="route('loans.index')" class="text-primary-600 dark:text-primary-400 hover:text-primary-900 text-sm font-medium">&larr; Quay lại</Link>
            </div>
            <dl class="border-t border-default sm:divide-y sm:divide-default">
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-content-muted">Loại khoản nợ</dt>
                    <dd class="mt-1 text-sm text-content sm:mt-0 sm:col-span-2">{{ loanTypeName() }}</dd>
                </div>
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-content-muted">Tổng số tiền gốc</dt>
                    <dd class="mt-1 text-sm text-content sm:mt-0 sm:col-span-2">{{ formatMoney(loan.principal_amount) }}</dd>
                </div>
                <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-content-muted">Ngày bắt đầu</dt>
                    <dd class="mt-1 text-sm text-content sm:mt-0 sm:col-span-2">{{ loan.started_at }}</dd>
                </div>
                <div v-if="loan.wallet" class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                    <dt class="text-sm font-medium text-content-muted">Ví liên kết</dt>
                    <dd class="mt-1 text-sm text-content sm:mt-0 sm:col-span-2">{{ loan.wallet.name }}</dd>
                </div>
                <template v-if="loan.type === 'bank'">
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-content-muted">Lãi suất</dt>
                        <dd class="mt-1 text-sm text-content sm:mt-0 sm:col-span-2">{{ loan.interest_rate }}% / năm</dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-content-muted">Phương pháp tính lãi</dt>
                        <dd class="mt-1 text-sm text-content sm:mt-0 sm:col-span-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="methodBadge().class">{{ methodBadge().label }}</span>
                        </dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-content-muted">Thời hạn vay</dt>
                        <dd class="mt-1 text-sm text-content sm:mt-0 sm:col-span-2">{{ loan.term_months }} tháng</dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-content-muted">Ngày thanh toán cố định</dt>
                        <dd class="mt-1 text-sm text-content sm:mt-0 sm:col-span-2">Ngày {{ paymentDay ?? '—' }} hàng tháng</dd>
                    </div>
                    <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-content-muted">Gốc còn lại</dt>
                        <dd class="mt-1 text-sm text-content sm:mt-0 sm:col-span-2">{{ formatMoney(loan.remaining_principal ?? 0) }}</dd>
                    </div>
                    <div v-if="method === 'custom'" class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                        <dt class="text-sm font-medium text-content-muted">Tổng trả hàng tháng</dt>
                        <dd class="mt-1 text-sm text-content sm:mt-0 sm:col-span-2">{{ formatMoney(loan.monthly_payment) }}</dd>
                    </div>
                </template>
            </dl>
        </div>

        <!-- Timeline for bank loans -->
        <div v-if="loan.type === 'bank' && timeline.length" class="bg-surface shadow overflow-hidden sm:rounded-lg">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-content">Lịch trả nợ dự kiến</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-default">
                    <thead class="bg-app">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-content-muted uppercase">Tháng</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-content-muted uppercase">Ngày trả</th>
                            <th v-if="method === 'daily' || method === 'homecredit'" class="px-6 py-3 text-left text-xs font-medium text-content-muted uppercase">Số ngày</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-content-muted uppercase">Tổng trả</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-content-muted uppercase">Tiền gốc</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-content-muted uppercase">Tiền lãi</th>
                            <th v-if="method === 'custom' || method === 'homecredit'" class="px-6 py-3 text-left text-xs font-medium text-content-muted uppercase">Phí</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-content-muted uppercase">Dư nợ còn lại</th>
                        </tr>
                    </thead>
                    <tbody class="bg-surface divide-y divide-default">
                        <template v-for="(row, i) in timeline" :key="i">
                            <tr v-if="row.type === 'early'" class="bg-sky-50 dark:bg-sky-900/30">
                                <td class="px-6 py-4 text-sm text-sky-800" :colspan="(method === 'daily' || method === 'homecredit') ? 2 : 1">
                                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-sky-100 dark:bg-sky-900/50 text-sky-800 dark:text-sky-200">TT trước</span>
                                </td>
                                <td class="px-6 py-4 text-sm font-medium text-sky-900">{{ fmtDate(row.payment?.paid_at) }}</td>
                                <td v-if="method === 'daily' || method === 'homecredit'" class="px-6 py-4 text-sm text-sky-600">—</td>
                                <td class="px-6 py-4 text-sm font-medium text-sky-900">{{ formatMoney(row.payment?.amount ?? 0) }}</td>
                                <td class="px-6 py-4 text-sm text-sky-600" :colspan="(method === 'custom' || method === 'homecredit') ? 4 : 3">
                                    <p>{{ row.note }}</p>
                                    <p v-if="row.period_due_date" class="text-xs mt-1">Kỳ đến hạn {{ fmtDate(row.period_due_date) }} · {{ row.payment?.note }}</p>
                                </td>
                            </tr>
                            <tr v-else :class="row.is_paid ? 'bg-green-50 dark:bg-green-900/30' : ''">
                                <td class="px-6 py-4 text-sm text-content-muted">
                                    {{ row.period?.month_index }}
                                    <span v-if="row.is_paid" class="ml-1 inline-flex px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200">Đã trả</span>
                                    <span v-else-if="row.period_payment" class="ml-1 inline-flex px-2 py-0.5 rounded text-xs font-medium bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200">Đã TT</span>
                                </td>
                                <td class="px-6 py-4 text-sm text-content">
                                    {{ fmtDate(row.period_due_date) }}
                                    <span v-if="scheduleDateLabel(row)" class="block text-xs text-content-muted">Lịch: {{ scheduleDateLabel(row) }}</span>
                                </td>
                                <td v-if="method === 'daily' || method === 'homecredit'" class="px-6 py-4 text-sm text-content-muted">{{ row.period?.days }} ngày</td>
                                <td class="px-6 py-4 text-sm font-medium text-content">{{ formatMoney(row.period?.payment ?? 0) }}</td>
                                <td class="px-6 py-4 text-sm text-content-muted">{{ formatMoney(row.period?.principal ?? 0) }}</td>
                                <td class="px-6 py-4 text-sm text-content-muted">{{ formatMoney(row.period?.interest ?? 0) }}</td>
                                <td v-if="method === 'custom' || method === 'homecredit'" class="px-6 py-4 text-sm text-content-muted">{{ formatMoney(row.period?.fee ?? 0) }}</td>
                                <td class="px-6 py-4 text-sm text-content-muted">{{ formatMoney(row.period?.remaining_principal ?? 0) }}</td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payment history -->
        <div v-if="sortedPayments.length" class="bg-surface shadow overflow-hidden sm:rounded-lg mt-6">
            <div class="px-4 py-5 sm:px-6">
                <h3 class="text-lg leading-6 font-medium text-content">Lịch sử thanh toán</h3>
            </div>
            <ul class="divide-y divide-default border-t border-default">
                <li v-for="payment in sortedPayments" :key="payment.id" class="px-4 py-4 sm:px-6">
                    <div class="flex justify-between items-start gap-2">
                        <div>
                            <span v-if="loan.type === 'bank'" class="text-xs px-2 py-0.5 rounded-full" :class="payment.is_early ? 'bg-sky-100 dark:bg-sky-900/50 text-sky-800 dark:text-sky-200' : 'bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200'">{{ payment.kind_label }}</span>
                            <p class="mt-1 font-medium text-content">{{ formatMoney(payment.amount) }} · {{ paymentDate(payment) }}</p>
                            <p v-if="payment.period_due_date" class="text-xs text-content-muted">Kỳ đến hạn {{ fmtDate(payment.period_due_date) }}</p>
                            <p class="text-sm text-gray-600 dark:text-slate-500">{{ payment.note ?? '—' }}</p>
                        </div>
                    </div>
                </li>
            </ul>
        </div>
        <div v-else-if="loan.type !== 'bank'" class="bg-surface shadow overflow-hidden sm:rounded-lg mt-6">
            <div class="px-4 py-5 sm:px-6"><h3 class="text-lg font-medium text-content">Lịch sử thanh toán</h3></div>
            <p class="px-4 py-8 text-sm text-content-muted text-center">Chưa có lịch sử thanh toán nào.</p>
        </div>
    </AppLayout>
</template>
