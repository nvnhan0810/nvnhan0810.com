<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Chart from 'chart.js/auto';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatMoney, formatDateVi } from '@/domain';

const CHART_PERIODS = {
    day: { label: 'Ngày', range: '14 ngày gần nhất' },
    week: { label: 'Tuần', range: '8 tuần gần nhất' },
    month: { label: 'Tháng', range: '6 tháng gần nhất' },
    year: { label: 'Năm', range: '5 năm gần nhất' },
};

const props = defineProps({
    totalDebt: { type: Number, default: 0 },
    wallets: { type: Array, default: () => [] },
    recentTransactions: { type: Array, default: () => [] },
    upcomingReminders: { type: Array, default: () => [] },
    cashFlowStats: { type: Object, required: true },
    chartPeriod: { type: String, default: 'month' },
    alertDays: { type: Number, default: 7 },
    walletsCount: { type: Number, default: 0 },
    unsettledLoansCount: { type: Number, default: 0 },
});

const insufficientReminders = computed(() =>
    props.upcomingReminders.filter((r) => r.insufficient_funds).length,
);

const cur = computed(() => props.cashFlowStats?.current ?? { income: 0, expense: 0, net: 0 });

const chartCanvas = ref(null);
let chartInstance = null;

function isDark() {
    return document.documentElement.classList.contains('dark');
}

function fmtCompact(v) {
    return new Intl.NumberFormat('vi-VN', { notation: 'compact', maximumFractionDigits: 1 }).format(v);
}

function buildChart() {
    if (!chartCanvas.value || !props.cashFlowStats?.has_data) return;

    const series = props.cashFlowStats.series ?? [];
    chartInstance?.destroy();

    Chart.defaults.color = isDark() ? '#9ca3af' : '#6b7280';
    Chart.defaults.borderColor = isDark() ? '#334155' : '#f3f4f6';
    Chart.defaults.font.family = "'Instrument Sans', ui-sans-serif, system-ui, sans-serif";

    chartInstance = new Chart(chartCanvas.value, {
        type: 'bar',
        data: {
            labels: series.map((m) => m.label),
            datasets: [
                { label: 'Thu', data: series.map((m) => m.income), backgroundColor: '#22c55e', borderRadius: 6, maxBarThickness: 40 },
                { label: 'Chi', data: series.map((m) => m.expense), backgroundColor: '#ef4444', borderRadius: 6, maxBarThickness: 40 },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 12, weight: '500' }, usePointStyle: true } },
                tooltip: {
                    backgroundColor: isDark() ? '#1e293b' : '#ffffff',
                    titleColor: isDark() ? '#f8fafc' : '#111827',
                    bodyColor: isDark() ? '#cbd5e1' : '#4b5563',
                    borderColor: isDark() ? '#334155' : '#e5e7eb',
                    borderWidth: 1,
                    padding: 10,
                    boxPadding: 4,
                    cornerRadius: 8,
                    callbacks: {
                        title: (items) => series[items[0].dataIndex]?.label_full || items[0].label,
                        label: (ctx) => `${ctx.dataset.label}: ${new Intl.NumberFormat('vi-VN').format(ctx.raw)} ₫`,
                    },
                },
            },
            scales: {
                y: { beginAtZero: true, border: { display: false }, ticks: { callback: (v) => fmtCompact(v) } },
                x: { border: { display: false }, grid: { display: false } },
            },
        },
    });
}

onMounted(() => buildChart());
watch(() => [props.cashFlowStats?.series, props.chartPeriod], () => buildChart(), { deep: true });
onBeforeUnmount(() => chartInstance?.destroy());

function formatDueDate(date) {
    if (!date) return '';
    return formatDateVi(date);
}

function txSign(tx) {
    if (tx.type === 'income') return '+';
    if (tx.type === 'expense') return '-';
    return '';
}

function txColor(tx) {
    if (tx.type === 'income') return 'text-green-600 dark:text-green-400';
    return 'text-content';
}
</script>

<template>
    <Head title="Tổng quan" />
    <AppLayout title="Tổng quan">
        <div class="mb-8 pt-2">
            <div class="flex justify-center gap-6 flex-wrap">
                <Link :href="route('transactions.create')" class="flex flex-col items-center gap-2 group">
                    <div class="w-14 h-14 rounded-full bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                    </div>
                    <span class="text-xs font-medium text-content-secondary">Thêm GD</span>
                </Link>
                <Link :href="route('transactions.bulk')" class="flex flex-col items-center gap-2 group">
                    <div class="w-14 h-14 rounded-full bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h10M4 18h7" /></svg>
                    </div>
                    <span class="text-xs font-medium text-content-secondary">Cả ngày</span>
                </Link>
                <Link :href="route('transactions.create', { type: 'transfer' })" class="flex flex-col items-center gap-2 group">
                    <div class="w-14 h-14 rounded-full bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                    </div>
                    <span class="text-xs font-medium text-content-secondary">Chuyển ví</span>
                </Link>
                <Link :href="route('transaction-templates.index')" class="flex flex-col items-center gap-2 group">
                    <div class="w-14 h-14 rounded-full bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                    </div>
                    <span class="text-xs font-medium text-content-secondary">Mẫu GD</span>
                </Link>
                <a href="#report" class="flex flex-col items-center gap-2 group">
                    <div class="w-14 h-14 rounded-full bg-orange-50 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 flex items-center justify-center group-hover:scale-105 transition-transform">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                    </div>
                    <span class="text-xs font-medium text-content-secondary">Báo cáo</span>
                </a>
            </div>
        </div>

        <!-- Pinned wallets -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4 px-1">
                <h3 class="text-lg font-bold text-content">Ví của bạn</h3>
                <Link :href="route('wallets.index')" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">Quản lý ví</Link>
            </div>
            <div class="flex overflow-x-auto snap-x snap-mandatory gap-4 pb-4 -mx-4 px-4 sm:mx-0 sm:px-0 hide-scrollbar">
                <Link
                    v-for="wallet in wallets"
                    :key="wallet.id"
                    :href="route('wallets.index')"
                    class="snap-start shrink-0 w-72 h-44 rounded-2xl shadow-md p-5 flex flex-col justify-between transition-transform hover:scale-[1.02] active:scale-95 cursor-pointer relative overflow-hidden"
                    :class="wallet.is_credit_card ? 'bg-gradient-to-br from-indigo-600 to-purple-700 text-white' : 'bg-gradient-to-br from-slate-800 to-slate-900 text-white'"
                >
                    <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10 blur-xl" />
                    <div class="absolute -left-6 -bottom-6 w-32 h-32 rounded-full bg-white/5 blur-xl" />
                    <div class="relative z-10 flex justify-between items-start">
                        <div>
                            <p class="font-semibold text-lg tracking-wide">{{ wallet.name }}</p>
                            <p class="text-xs opacity-80">{{ wallet.type_label }}</p>
                        </div>
                        <div class="w-8 h-8 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg>
                        </div>
                    </div>
                    <div class="relative z-10">
                        <template v-if="wallet.is_credit_card">
                            <p class="text-[10px] font-medium uppercase tracking-wider opacity-70 mb-1">Dư nợ</p>
                            <p class="font-bold text-2xl tracking-tight">{{ formatMoney(wallet.outstanding_balance ?? 0, false) }} <span class="text-lg font-semibold opacity-80">₫</span></p>
                            <div class="mt-2 flex justify-between items-center text-xs opacity-90">
                                <span>Hạn mức: {{ formatMoney(wallet.credit_limit ?? 0, false) }}</span>
                                <span>Còn: {{ formatMoney(wallet.spendable_balance ?? 0, false) }}</span>
                            </div>
                        </template>
                        <template v-else>
                            <p class="text-[10px] font-medium uppercase tracking-wider opacity-70 mb-1">Số dư</p>
                            <p class="font-bold text-2xl tracking-tight">{{ formatMoney(wallet.balance, false) }} <span class="text-lg font-semibold opacity-80">₫</span></p>
                        </template>
                    </div>
                </Link>
                <div v-if="!wallets.length" class="w-full bg-surface rounded-2xl border border-dashed border-strong p-8 text-center text-sm text-content-muted shadow-sm">
                    Chưa có ví nào được ghim.<br>
                    <Link :href="route('wallets.index')" class="text-primary-600 dark:text-primary-400 font-semibold hover:underline mt-2 inline-block">Ghim ví trên trang Ví</Link>
                </div>
            </div>
        </div>

        <!-- Debt & reminders summary -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
            <div class="bg-surface rounded-2xl shadow-sm border border-subtle p-5 flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wider text-content-muted">Tổng nợ</p>
                    <p class="mt-1 text-2xl font-bold text-red-600 dark:text-red-400 tracking-tight">{{ formatMoney(totalDebt, false) }} <span class="text-lg font-semibold opacity-80">₫</span></p>
                    <Link :href="route('loans.index')" class="text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline mt-2 inline-block">Xem khoản vay →</Link>
                </div>
                <div class="w-12 h-12 rounded-full bg-red-50 dark:bg-red-900/20 text-red-500 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                </div>
            </div>
            <div class="bg-surface rounded-2xl shadow-sm border border-subtle p-5 flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wider text-content-muted">Nhắc nhở thanh toán</p>
                    <p class="mt-1 text-2xl font-bold tracking-tight" :class="insufficientReminders ? 'text-red-600 dark:text-red-400' : 'text-content'">
                        {{ upcomingReminders.length }} <span class="text-lg font-medium opacity-70 text-content-muted">khoản sắp tới</span>
                    </p>
                    <a href="#reminders" class="text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline mt-2 inline-block">Xem chi tiết →</a>
                </div>
                <div class="w-12 h-12 rounded-full bg-amber-50 dark:bg-amber-900/20 text-amber-500 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                </div>
            </div>
        </div>

        <!-- Cash flow -->
        <div id="report" class="mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 px-1">
                <div>
                    <h3 class="text-lg font-bold text-content">Thu – Chi</h3>
                    <span class="text-xs text-content-muted">{{ cashFlowStats.range_label }}</span>
                </div>
                <div class="inline-flex rounded-xl border border-subtle bg-surface/50 p-1 shadow-sm overflow-x-auto hide-scrollbar">
                    <Link
                        v-for="(meta, key) in CHART_PERIODS"
                        :key="key"
                        :href="route('dashboard', { period: key })"
                        class="px-4 py-1.5 rounded-lg whitespace-nowrap text-sm font-medium transition-colors"
                        :class="chartPeriod === key ? 'bg-surface shadow-sm border border-subtle text-primary-600 dark:text-primary-400' : 'text-content-muted hover:text-content'"
                    >
                        {{ meta.label }}
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3 mb-5">
                <div class="bg-surface rounded-2xl shadow-sm border border-subtle p-4">
                    <p class="text-[11px] font-medium uppercase tracking-wider text-content-muted mb-1">Thu vào</p>
                    <p class="text-lg sm:text-xl font-bold text-green-600 dark:text-green-400 tracking-tight">{{ formatMoney(cur.income ?? 0, false) }}</p>
                </div>
                <div class="bg-surface rounded-2xl shadow-sm border border-subtle p-4">
                    <p class="text-[11px] font-medium uppercase tracking-wider text-content-muted mb-1">Chi ra</p>
                    <p class="text-lg sm:text-xl font-bold text-red-600 dark:text-red-400 tracking-tight">{{ formatMoney(cur.expense ?? 0, false) }}</p>
                </div>
                <div class="bg-surface rounded-2xl shadow-sm border border-subtle p-4">
                    <p class="text-[11px] font-medium uppercase tracking-wider text-content-muted mb-1">Còn lại</p>
                    <p class="text-lg sm:text-xl font-bold tracking-tight" :class="(cur.net ?? 0) >= 0 ? 'text-content' : 'text-red-600 dark:text-red-400'">{{ formatMoney(cur.net ?? 0, false) }}</p>
                </div>
            </div>

            <div v-if="cashFlowStats.has_data" class="bg-surface rounded-2xl border border-subtle p-5 shadow-sm">
                <p class="text-sm font-semibold text-content mb-4">Thống kê {{ (cashFlowStats.period_label || '').toLowerCase() }}</p>
                <div class="h-56">
                    <canvas ref="chartCanvas" />
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 pb-6">
            <div>
                <div class="flex items-center justify-between mb-4 px-1">
                    <h3 class="text-lg font-bold text-content">Giao dịch gần đây</h3>
                    <Link :href="route('transactions.index')" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">Tất cả</Link>
                </div>
                <div class="bg-surface shadow-sm rounded-2xl border border-subtle overflow-hidden">
                    <div v-for="tx in recentTransactions" :key="tx.id" class="block px-5 py-4 border-b border-subtle last:border-0">
                        <div class="flex justify-between items-center gap-4">
                            <div class="flex items-center gap-4 overflow-hidden">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0" :class="tx.type === 'income' ? 'bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400' : 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400'">
                                    <svg v-if="tx.type === 'income'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m0-16l-4 4m4-4l4 4" /></svg>
                                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20V4m0 16l-4-4m4 4l4-4" /></svg>
                                </div>
                                <div class="overflow-hidden">
                                    <p class="font-semibold text-content text-sm line-clamp-1">{{ tx.description }}</p>
                                    <p class="text-[11px] text-content-muted mt-0.5">{{ tx.wallet?.name }} · {{ tx.transacted_at_label || formatDateVi(tx.transacted_at) }}</p>
                                </div>
                            </div>
                            <p class="font-bold text-sm shrink-0" :class="txColor(tx)">{{ txSign(tx) }}{{ formatMoney(tx.amount, false) }} ₫</p>
                        </div>
                    </div>
                    <div v-if="!recentTransactions.length" class="px-5 py-8 text-sm text-content-muted text-center flex flex-col items-center">
                        <svg class="w-10 h-10 text-gray-300 dark:text-slate-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        Chưa có giao dịch gần đây.
                    </div>
                </div>
            </div>

            <div v-if="upcomingReminders.length" id="reminders">
                <h3 class="text-lg font-bold text-content mb-4 px-1">Sắp đến hạn <span class="text-sm font-normal text-content-muted">({{ alertDays }} ngày)</span></h3>
                <div class="space-y-3">
                    <div
                        v-for="(reminder, i) in upcomingReminders"
                        :key="i"
                        class="rounded-2xl border p-5 flex flex-col gap-3"
                        :class="reminder.insufficient_funds ? 'bg-red-50 dark:bg-red-900/10 border-red-200 dark:border-red-900/50 shadow-sm' : (reminder.kind === 'loan' ? 'bg-primary-50/50 dark:bg-primary-900/10 border-primary-200 dark:border-primary-900/50' : 'bg-surface border-subtle shadow-sm')"
                    >
                        <div>
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span class="font-bold text-base" :class="reminder.insufficient_funds ? 'text-red-900 dark:text-red-200' : 'text-content'">{{ reminder.name }}</span>
                                <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-md" :class="reminder.type_badge_class">{{ reminder.type_label }}</span>
                                <span v-if="reminder.insufficient_funds" class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-md bg-red-600 dark:bg-red-500 text-white animate-pulse">Thiếu tiền!</span>
                            </div>
                            <p class="text-sm font-medium" :class="reminder.insufficient_funds ? 'text-red-700 dark:text-red-400' : 'text-content-secondary'">
                                {{ formatMoney(reminder.amount, false) }} ₫
                                <template v-if="reminder.wallet_name"> · <span class="opacity-80">{{ reminder.wallet_name }}</span></template>
                            </p>
                            <p class="text-xs text-content-muted mt-1">Đến hạn: {{ formatDueDate(reminder.due_date) }}</p>
                        </div>
                        <div class="flex justify-end mt-1">
                            <Link
                                v-if="reminder.pay_url"
                                :href="reminder.pay_url"
                                class="text-sm font-semibold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30 px-4 py-2 rounded-lg hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors"
                            >
                                {{ reminder.kind === 'loan' ? 'Thanh toán ngay' : 'Ghi giao dịch' }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
