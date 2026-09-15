<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import { formatMoney, todayVi } from '@/domain';

const props = defineProps({
    loans: { type: Array, default: () => [] },
    totalRemaining: { type: Number, default: 0 },
    totalLendRemaining: { type: Number, default: 0 },
    wallets: { type: Array, default: () => [] },
});

const paymentModalOpen = ref(false);
const settleModalOpen = ref(false);
const selectedLoan = ref(null);

const paymentForm = useForm({
    loan_id: '',
    wallet_id: props.wallets[0]?.id ?? '',
    amount: '',
    paid_at: todayVi(),
    note: '',
    period_id: '',
});

const settleForm = useForm({
    wallet_id: props.wallets[0]?.id ?? '',
    amount: '',
    paid_at: todayVi(),
    note: 'Tất toán',
    remaining_principal: '',
});

const loansIndex = computed(() =>
    Object.fromEntries(props.loans.map((l) => [l.id, l])),
);

function cashFlowHint(type, action) {
    if (action === 'payment') {
        if (type === 'lend') return 'Thu vào ví (thu hồi cho mượn)';
        return 'Chi từ ví (trả nợ/vay)';
    }
    return '';
}

function openPaymentModal(loan, periodId = null, amount = null) {
    selectedLoan.value = loan;
    paymentForm.loan_id = loan.id;
    paymentForm.period_id = periodId || '';
    paymentForm.amount = amount || loan.monthly_payment || loan.payoff_remaining || '';
    paymentForm.note = loan.monthly_payment ? 'Thanh toán định kỳ' : 'Thanh toán nợ';
    paymentForm.wallet_id = loan.wallet_id || props.wallets[0]?.id || '';
    paymentForm.paid_at = todayVi();
    paymentModalOpen.value = true;
}

function openSettleModal(loan) {
    selectedLoan.value = loan;
    settleForm.amount = loan.payoff_remaining || '';
    settleForm.note = 'Tất toán';
    settleForm.wallet_id = loan.wallet_id || props.wallets[0]?.id || '';
    settleForm.remaining_principal = loan.remaining_principal ?? '';
    settleForm.paid_at = todayVi();
    settleModalOpen.value = true;
}

function submitPayment() {
    paymentForm.post(route('payments.store'), {
        onSuccess: () => { paymentModalOpen.value = false; },
    });
}

function submitSettle() {
    if (!selectedLoan.value) return;
    settleForm.post(route('loans.settle', selectedLoan.value.id), {
        onSuccess: () => { settleModalOpen.value = false; },
    });
}

function loanTypeLabel(loan) {
    if (loan.type === 'bank') {
        const method = loan.interest_calculation_method ?? 'monthly';
        let label = 'Vay Ngân hàng';
        if (method === 'homecredit') label += ' (Home Credit EMI)';
        else if (method === 'daily') label += ' (Tính theo ngày)';
        else if (method === 'custom') label += ' (Custom)';
        return label;
    }
    if (loan.type === 'borrow') return 'Mượn Nợ';
    return 'Cho Mượn';
}

function borderClass(loan) {
    if (loan.type === 'lend') return 'border-green-500';
    if (loan.type === 'bank') return 'border-red-500';
    return 'border-orange-500';
}

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const payLoan = params.get('pay');
    if (payLoan && loansIndex.value[payLoan]) {
        openPaymentModal(
            loansIndex.value[payLoan],
            params.get('period'),
            params.get('amount'),
        );
    }
});
</script>

<template>
    <Head title="Kế hoạch tài chính" />
    <AppLayout title="Kế hoạch tài chính">
        <div class="mb-6">
            <h2 class="text-2xl font-bold leading-7 text-content sm:text-3xl sm:truncate">Kế hoạch tài chính</h2>
        </div>

        <div class="flex flex-wrap border-b border-subtle mb-6">
            <Link :href="route('loans.index')" class="px-4 py-2 border-b-2 border-primary-600 dark:border-primary-400 text-primary-600 dark:text-primary-400 font-semibold text-sm">Khoản vay & Nợ</Link>
            <Link :href="route('recurring-items.index')" class="px-4 py-2 border-b-2 border-transparent text-content-muted hover:text-content font-medium text-sm transition-colors">Thu chi cố định</Link>
            <Link :href="route('fixed-expenses.index')" class="px-4 py-2 border-b-2 border-transparent text-content-muted hover:text-content font-medium text-sm transition-colors">Tổng hợp chi cố định</Link>
        </div>

        <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="bg-surface shadow rounded-lg border border-subtle p-4 sm:p-5">
                <p class="text-sm text-content-muted">Tổng gốc còn lại (Vay + Nợ)</p>
                <p class="mt-1 text-2xl font-bold text-content">{{ formatMoney(totalRemaining, false) }} ₫</p>
            </div>
            <div class="bg-surface shadow rounded-lg border border-subtle p-4 sm:p-5">
                <p class="text-sm text-content-muted">Tổng đang cho mượn (còn lại)</p>
                <p class="mt-1 text-2xl font-bold text-content">{{ formatMoney(totalLendRemaining, false) }} ₫</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="loan in loans"
                :key="loan.id"
                class="bg-surface overflow-hidden shadow rounded-lg divide-y divide-default border-l-4"
                :class="borderClass(loan)"
            >
                <div class="px-4 py-5 sm:px-6 flex justify-between items-start">
                    <div>
                        <Link :href="route('loans.show', loan.id)" class="hover:underline">
                            <h3 class="text-lg leading-6 font-medium text-content">{{ loan.name }}</h3>
                        </Link>
                        <p class="mt-1 max-w-2xl text-sm text-content-muted">{{ loanTypeLabel(loan) }}</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="loan.type === 'lend' ? 'bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200' : 'bg-red-100 text-red-800'">
                        {{ loan.type === 'lend' ? 'Tài sản' : 'Nợ phải trả' }}
                    </span>
                </div>

                <div class="px-4 py-5 sm:p-6">
                    <template v-if="loan.type === 'bank'">
                        <div class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-content-muted">Thời gian còn lại</dt>
                                <dd class="mt-1 text-2xl font-semibold text-content">{{ loan.remaining_months }} / {{ loan.term_months }} tháng</dd>
                                <p class="mt-1 text-xs text-content-muted">Đã trả {{ loan.months_passed ?? 0 }} tháng</p>
                            </div>
                            <div class="flex justify-between">
                                <div>
                                    <dt class="text-xs font-medium text-content-muted">Gốc còn lại</dt>
                                    <dd class="mt-1 text-sm font-bold text-content">{{ formatMoney(loan.remaining_principal ?? 0, false) }} ₫</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-content-muted">Lãi dự tính còn lại</dt>
                                    <dd class="mt-1 text-sm font-bold text-content">{{ formatMoney(loan.remaining_interest ?? 0, false) }} ₫</dd>
                                </div>
                            </div>
                            <div class="pt-2 border-t border-subtle">
                                <dt class="text-xs font-medium text-content-muted">Đóng hàng tháng</dt>
                                <dd class="text-sm text-content-secondary">{{ formatMoney(loan.monthly_payment, false) }} ₫</dd>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <div class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-content-muted">Còn lại phải {{ loan.type === 'lend' ? 'thu' : 'trả' }}</dt>
                                <dd class="mt-1 text-2xl font-semibold text-content">{{ formatMoney(loan.remaining_amount ?? 0, false) }} ₫</dd>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-content-muted">Tổng: {{ formatMoney(loan.principal_amount, false) }}</span>
                                <span class="text-green-600 dark:text-green-400">Đã trả: {{ formatMoney(loan.principal_amount - (loan.remaining_amount ?? 0), false) }}</span>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="px-4 py-4 sm:px-6 bg-app flex justify-between items-center">
                    <button type="button" class="text-primary-600 dark:text-primary-400 hover:text-primary-900 font-medium text-sm" @click="openPaymentModal(loan)">Thanh toán</button>
                    <button type="button" class="text-content-muted hover:text-green-600 dark:text-green-400 text-sm" @click="openSettleModal(loan)">Tất toán</button>
                </div>
            </div>

            <div v-if="!loans.length" class="col-span-3 text-center py-12">
                <h3 class="mt-2 text-sm font-medium text-content">Chưa có dữ liệu</h3>
                <p class="mt-1 text-sm text-content-muted">Bắt đầu bằng cách tạo khoản vay hoặc cho mượn mới.</p>
                <Link :href="route('loans.create')" class="mt-6 inline-flex items-center px-4 py-2 rounded-md text-white bg-primary-600 hover:bg-primary-700 text-sm font-medium">Tạo mới</Link>
            </div>
        </div>

        <!-- Payment modal -->
        <div v-if="paymentModalOpen" class="fixed z-50 inset-0 overflow-y-auto">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-24 sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500/75" @click="paymentModalOpen = false" />
                <div class="relative inline-block align-bottom bg-surface rounded-lg shadow-xl sm:my-8 sm:max-w-lg sm:w-full w-full">
                    <form @submit.prevent="submitPayment">
                        <div class="bg-surface px-4 pt-5 pb-4 sm:p-6">
                            <h3 class="text-lg font-medium text-content">Thanh toán cho: {{ selectedLoan?.name }}</h3>
                            <div class="mt-4 space-y-4">
                                <p v-if="selectedLoan?.type === 'bank'" class="text-xs text-amber-700 bg-amber-50 dark:bg-amber-900/30 rounded-md p-2">
                                    TT trước ngày cố định → không trừ gốc (hiện trên lịch kỳ). Từ 06/2026 chỉ TT đúng/sau ngày kỳ mới trừ gốc.
                                </p>
                                <p v-if="selectedLoan" class="text-xs text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/50 rounded-md p-2">
                                    {{ cashFlowHint(selectedLoan.type, 'payment') }}
                                </p>
                                <div>
                                    <label class="block text-sm font-medium text-content-secondary">Ví</label>
                                    <select v-model="paymentForm.wallet_id" required class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2 text-sm">
                                        <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-content-secondary">Số tiền</label>
                                    <div class="field-money"><MoneyInput v-model="paymentForm.amount" required /></div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-content-secondary">Ngày thanh toán</label>
                                    <div class="field-date"><DatePicker v-model="paymentForm.paid_at" required /></div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-content-secondary">Ghi chú</label>
                                    <input v-model="paymentForm.note" type="text" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2 text-sm" />
                                </div>
                            </div>
                        </div>
                        <div class="bg-app px-4 py-3 sm:flex sm:flex-row-reverse gap-2">
                            <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-primary-600 text-white rounded-md text-sm font-medium disabled:opacity-50" :disabled="paymentForm.processing">Xác nhận thanh toán</button>
                            <button type="button" class="mt-2 sm:mt-0 w-full sm:w-auto px-4 py-2 border border-strong rounded-md text-sm" @click="paymentModalOpen = false">Hủy</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Settle modal -->
        <div v-if="settleModalOpen" class="fixed z-[60] inset-0 overflow-y-auto">
            <div class="flex items-end justify-center min-h-screen px-4 pb-24 pt-4">
                <div class="fixed inset-0 bg-gray-500/75" @click="settleModalOpen = false" />
                <div class="relative bg-surface rounded-lg shadow-xl w-full max-w-lg">
                    <form @submit.prevent="submitSettle">
                        <div class="p-6 space-y-4">
                            <h3 class="text-lg font-medium">Tất toán: {{ selectedLoan?.name }}</h3>
                            <p class="text-xs text-content-muted">Ghi nốt số còn lại vào ví (để 0 nếu đã trả hết).</p>
                            <p v-if="selectedLoan" class="text-xs text-green-700 bg-green-50 dark:bg-green-900/30 p-2 rounded">{{ cashFlowHint(selectedLoan.type, 'payment') }}</p>
                            <div>
                                <label class="block text-sm font-medium text-content-secondary">Ví</label>
                                <select v-model="settleForm.wallet_id" required class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2 text-sm">
                                    <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-content-secondary">Số tiền (₫)</label>
                                <div class="field-money"><MoneyInput v-model="settleForm.amount" /></div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-content-secondary">Ngày</label>
                                <div class="field-date"><DatePicker v-model="settleForm.paid_at" /></div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-content-secondary">Ghi chú</label>
                                <input v-model="settleForm.note" type="text" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2" />
                            </div>
                        </div>
                        <div class="bg-app px-4 py-3 flex gap-2 justify-end">
                            <button type="button" class="px-4 py-2 border rounded-md text-sm" @click="settleModalOpen = false">Hủy</button>
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium disabled:opacity-50" :disabled="settleForm.processing">Tất toán</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <Link
            v-if="!paymentModalOpen && !settleModalOpen"
            :href="route('loans.create')"
            class="fixed z-40 right-4 md:right-8 bottom-[calc(5.5rem+env(safe-area-inset-bottom,0px))] md:bottom-8 inline-flex items-center justify-center w-14 h-14 rounded-full bg-primary-600 dark:bg-primary-500 text-white shadow-lg hover:bg-primary-700 active:scale-95 transition"
            aria-label="Tạo khoản vay mới"
        >
            <svg class="w-7 h-7" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" /></svg>
        </Link>
    </AppLayout>
</template>