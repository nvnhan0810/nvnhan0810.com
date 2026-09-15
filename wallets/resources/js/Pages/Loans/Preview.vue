<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import { formatMoney } from '@/domain';
import { applyCascadeInPlace, type ScheduleRow } from '@/domain/lending/schedule-cascade';

type LoanDraft = {
    type: string;
    name: string;
    principal_amount: number;
    started_at: string;
    started_at_label: string;
    wallet_id: number | string | null;
    record_cash_flow: boolean;
    received_amount: number | null;
    interest_rate: number;
    interest_calculation_method: string;
    term_months: number;
    months_paid: number;
    monthly_payment: number;
    collection_fee: number;
    payment_day: number;
};

const props = defineProps<{
    loan: LoanDraft;
    schedule: ScheduleRow[];
    editable: boolean;
}>();

const rows = ref<ScheduleRow[]>(props.schedule.map((r) => ({ ...r })));

watch(
    () => props.schedule,
    (next) => {
        rows.value = next.map((r) => ({ ...r }));
    },
);

const methodLabel = computed(() => ({
    monthly: 'Theo tháng',
    daily: 'Actual/365 (Ngân hàng)',
    homecredit: 'Home Credit (EMI + Actual/365)',
}[props.loan.interest_calculation_method] ?? props.loan.interest_calculation_method));

function onRowEdit(): void {
    applyCascadeInPlace(
        props.loan.principal_amount,
        rows.value,
        props.loan.monthly_payment,
    );
}

const form = useForm({
    ...props.loan,
    custom_schedule: [] as Array<Record<string, unknown>>,
});

function confirmCreate(): void {
    form.custom_schedule = rows.value.map((r) => ({
        month_index: r.month_index,
        paid_at: r.due_date,
        due_date: r.due_date,
        payment: r.payment,
        principal: r.principal,
        interest: r.interest,
        fee: r.fee,
        remaining_principal: r.remaining_principal,
    }));
    form.transform((data) => ({
        ...data,
        started_at: props.loan.started_at_label,
    })).post(route('loans.store'));
}
</script>

<template>
    <Head title="Xem trước lịch trả góp" />
    <AppLayout title="Xem trước lịch trả góp">
        <div class="max-w-6xl mx-auto space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold text-content">Xem trước lịch trả góp</h1>
                    <p class="mt-1 text-sm text-content-muted">
                        Kiểm tra / chỉnh lãi và phí từng kỳ. Gốc và dư nợ được tính lại tự động.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link
                        :href="route('loans.create')"
                        class="px-4 py-2 border border-strong rounded-md text-sm bg-surface hover:bg-surface-hover"
                    >
                        Quay lại sửa
                    </Link>
                    <button
                        type="button"
                        class="px-4 py-2 rounded-md text-sm font-medium text-white bg-primary-600 hover:bg-primary-700"
                        :disabled="form.processing"
                        @click="confirmCreate"
                    >
                        Xác nhận tạo khoản vay
                    </button>
                </div>
            </div>

            <dl class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 text-sm bg-surface border border-default rounded-xl p-4">
                <div>
                    <dt class="text-content-muted">Tên</dt>
                    <dd class="font-medium">{{ loan.name }}</dd>
                </div>
                <div>
                    <dt class="text-content-muted">Gốc</dt>
                    <dd class="font-medium">{{ formatMoney(loan.principal_amount) }}</dd>
                </div>
                <div>
                    <dt class="text-content-muted">Lãi suất</dt>
                    <dd class="font-medium">{{ loan.interest_rate }}% / năm</dd>
                </div>
                <div>
                    <dt class="text-content-muted">Cách tính</dt>
                    <dd class="font-medium">{{ methodLabel }}</dd>
                </div>
                <div>
                    <dt class="text-content-muted">EMI / tháng</dt>
                    <dd class="font-medium">{{ formatMoney(loan.monthly_payment) }}</dd>
                </div>
                <div>
                    <dt class="text-content-muted">Phí thu hộ</dt>
                    <dd class="font-medium">{{ formatMoney(loan.collection_fee) }}</dd>
                </div>
                <div>
                    <dt class="text-content-muted">Kỳ hạn</dt>
                    <dd class="font-medium">{{ loan.term_months }} tháng</dd>
                </div>
                <div>
                    <dt class="text-content-muted">Ngày TT</dt>
                    <dd class="font-medium">Ngày {{ loan.payment_day }}</dd>
                </div>
            </dl>

            <div class="overflow-x-auto border border-default rounded-xl bg-surface">
                <table class="min-w-full text-sm divide-y divide-default">
                    <thead class="bg-app text-xs uppercase text-content-muted">
                        <tr>
                            <th class="px-3 py-2 text-left">Kỳ</th>
                            <th class="px-3 py-2 text-left">Ngày trả</th>
                            <th class="px-3 py-2 text-right">Số ngày</th>
                            <th class="px-3 py-2 text-right">Lãi</th>
                            <th class="px-3 py-2 text-right">Phí</th>
                            <th class="px-3 py-2 text-right">Gốc</th>
                            <th class="px-3 py-2 text-right">Tổng trả</th>
                            <th class="px-3 py-2 text-right">Dư nợ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-subtle">
                        <tr v-for="row in rows" :key="row.month_index">
                            <td class="px-3 py-2 font-medium">{{ row.month_index }}</td>
                            <td class="px-3 py-2">{{ row.due_date_label || row.due_date }}</td>
                            <td class="px-3 py-2 text-right text-content-muted">{{ row.days ?? '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                <div v-if="editable" class="field-money-sm inline-block w-36">
                                    <MoneyInput
                                        v-model="row.interest"
                                        @update:model-value="onRowEdit"
                                    />
                                </div>
                                <span v-else>{{ formatMoney(row.interest, false) }}</span>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <div v-if="editable" class="field-money-sm inline-block w-28">
                                    <MoneyInput
                                        v-model="row.fee"
                                        @update:model-value="onRowEdit"
                                    />
                                </div>
                                <span v-else>{{ formatMoney(row.fee, false) }}</span>
                            </td>
                            <td class="px-3 py-2 text-right">{{ formatMoney(row.principal, false) }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ formatMoney(row.payment, false) }}</td>
                            <td class="px-3 py-2 text-right">{{ formatMoney(row.remaining_principal, false) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="text-xs text-content-muted">
                Kỳ cuối có thể khác EMI để tất toán dư nợ về 0. Sai số làm tròn được dồn vào kỳ cuối.
            </p>

            <div class="flex justify-end gap-2">
                <Link :href="route('loans.index')" class="px-4 py-2 border border-strong rounded-md text-sm">Hủy</Link>
                <button
                    type="button"
                    class="px-4 py-2 rounded-md text-sm font-medium text-white bg-primary-600 hover:bg-primary-700"
                    :disabled="form.processing"
                    @click="confirmCreate"
                >
                    Xác nhận tạo khoản vay
                </button>
            </div>
        </div>
    </AppLayout>
</template>
