<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDateVi, formatMoney } from '@/domain';
import {
    FIXED_EXPENSE_DEFAULT_CUSTOM_DAYS,
    FIXED_EXPENSE_DEFAULT_DURATION,
    FixedExpenseSource,
    isCustomGranularity,
    normalizeStartParam,
    startInputType,
    startInputValue,
} from '@/domain/reporting/fixed-expense';

type GranularityOption = { value: string; label: string };

type PeriodItem = {
    source: string;
    source_id: number | string;
    name: string;
    amount: number;
    due_date: string;
    type_label: string;
};

type Period = {
    index: number;
    key: string;
    label: string;
    start: string;
    end: string;
    total: number;
    items: PeriodItem[];
};

const props = defineProps<{
    granularity: string;
    start: string;
    duration: number;
    custom_days: number;
    min_start: string;
    periods: Period[];
    grand_total: number;
    granularity_options: GranularityOption[];
}>();

const granularity = ref(props.granularity);
const start = ref(startInputValue(props.granularity, props.start));
const duration = ref(props.duration || FIXED_EXPENSE_DEFAULT_DURATION);
const customDays = ref(props.custom_days || FIXED_EXPENSE_DEFAULT_CUSTOM_DAYS);

watch(
    () => [props.granularity, props.start, props.duration, props.custom_days] as const,
    ([g, s, d, c]) => {
        granularity.value = g;
        start.value = startInputValue(g, s);
        duration.value = d;
        customDays.value = c;
    },
);

const showCustomDays = computed(() => isCustomGranularity(granularity.value));
const startType = computed(() => startInputType(granularity.value));
const minStartValue = computed(() => startInputValue(granularity.value, props.min_start));

function applyFilters(): void {
    router.get(
        route('fixed-expenses.index'),
        {
            granularity: granularity.value,
            start: normalizeStartParam(granularity.value, start.value),
            duration: duration.value,
            custom_days: customDays.value,
        },
        { preserveState: true, replace: true },
    );
}

function onGranularityChange(): void {
    start.value = startInputValue(granularity.value, props.min_start);
    applyFilters();
}

function itemLink(item: PeriodItem): string | null {
    if (item.source === FixedExpenseSource.Loan) {
        return route('loans.show', item.source_id);
    }
    if (item.source === FixedExpenseSource.Recurring) {
        return route('recurring-items.index');
    }
    return null;
}
</script>

<template>
    <Head title="Tổng hợp chi cố định" />
    <AppLayout title="Tổng hợp chi cố định">
        <div class="mb-6">
            <h2 class="text-2xl font-bold leading-7 text-content sm:text-3xl">Kế hoạch tài chính</h2>
        </div>

        <div class="flex flex-wrap border-b border-subtle mb-6">
            <Link :href="route('loans.index')" class="px-4 py-2 border-b-2 border-transparent text-content-muted hover:text-content font-medium text-sm">Khoản vay & Nợ</Link>
            <Link :href="route('recurring-items.index')" class="px-4 py-2 border-b-2 border-transparent text-content-muted hover:text-content font-medium text-sm">Thu chi cố định</Link>
            <Link :href="route('fixed-expenses.index')" class="px-4 py-2 border-b-2 border-primary-600 dark:border-primary-400 text-primary-600 dark:text-primary-400 font-semibold text-sm">Tổng hợp chi cố định</Link>
        </div>

        <p class="mb-4 text-sm text-content-muted">
            Xem các khoản chi cố định và kỳ trả vay (chưa tất toán / còn trong hạn) theo tuần, tháng, năm hoặc kỳ tùy chỉnh.
        </p>

        <form class="bg-surface shadow rounded-lg border border-default p-4 sm:p-5 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end" @submit.prevent="applyFilters">
            <div>
                <label class="block text-sm font-medium text-content-secondary">Thời gian</label>
                <select v-model="granularity" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2" @change="onGranularityChange">
                    <option v-for="opt in granularity_options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-content-secondary">Bắt đầu</label>
                <input
                    v-model="start"
                    :type="startType"
                    :min="minStartValue"
                    class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2"
                />
            </div>
            <div>
                <label class="block text-sm font-medium text-content-secondary">Số kỳ</label>
                <input v-model.number="duration" type="number" min="1" max="24" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2" />
            </div>
            <div v-if="showCustomDays">
                <label class="block text-sm font-medium text-content-secondary">Độ dài mỗi kỳ (ngày)</label>
                <input v-model.number="customDays" type="number" min="1" max="366" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2" />
            </div>
            <div>
                <button type="submit" class="w-full sm:w-auto px-4 py-2 bg-primary-600 dark:bg-primary-500 text-white rounded-md text-sm font-medium">Áp dụng</button>
            </div>
        </form>

        <div class="mb-6 bg-surface shadow rounded-lg border border-default p-4 sm:p-5 flex flex-wrap items-baseline justify-between gap-2">
            <p class="text-sm text-content-muted">Tổng chi cố định trong khoảng đã chọn</p>
            <p class="text-2xl font-bold text-content">{{ formatMoney(grand_total) }}</p>
        </div>

        <div class="space-y-6">
            <section
                v-for="period in periods"
                :key="period.key"
                class="bg-surface shadow rounded-lg border border-default overflow-hidden"
            >
                <header class="px-4 sm:px-5 py-3 border-b border-subtle flex flex-wrap items-baseline justify-between gap-2 bg-gray-50/80 dark:bg-slate-800/40">
                    <h3 class="text-base font-semibold text-content">{{ period.label }}</h3>
                    <p class="text-sm font-medium text-content">{{ formatMoney(period.total) }}</p>
                </header>

                <ul v-if="period.items.length" class="divide-y divide-subtle">
                    <li
                        v-for="(item, idx) in period.items"
                        :key="`${period.key}-${item.source}-${item.source_id}-${item.due_date}-${idx}`"
                        class="px-4 sm:px-5 py-3 flex flex-wrap items-center justify-between gap-2"
                    >
                        <div class="min-w-0">
                            <p class="font-medium text-content truncate">
                                <a
                                    v-if="itemLink(item)"
                                    :href="itemLink(item) ?? '#'"
                                    class="hover:text-primary-600 dark:hover:text-primary-400"
                                >{{ item.name }}</a>
                                <span v-else>{{ item.name }}</span>
                            </p>
                            <p class="text-xs text-content-muted mt-0.5">
                                {{ item.type_label }} · đến hạn {{ formatDateVi(item.due_date) }}
                            </p>
                        </div>
                        <p class="text-sm font-semibold text-content whitespace-nowrap">{{ formatMoney(item.amount) }}</p>
                    </li>
                </ul>
                <p v-else class="px-4 sm:px-5 py-6 text-sm text-content-muted">Không có khoản chi cố định trong kỳ này.</p>
            </section>
        </div>
    </AppLayout>
</template>
