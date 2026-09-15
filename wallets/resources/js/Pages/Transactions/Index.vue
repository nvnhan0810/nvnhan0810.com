<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatMoney, formatDateVi, TX_TYPES } from '@/domain';

const props = defineProps({
    transactions: { type: Object, required: true },
    wallets: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const filterForm = {
    wallet_id: props.filters.wallet_id ?? '',
    type: props.filters.type ?? '',
};

function applyFilters() {
    router.get(route('transactions.index'), {
        wallet_id: filterForm.wallet_id || undefined,
        type: filterForm.type || undefined,
    }, { preserveState: true });
}

const grouped = computed(() => {
    const map = new Map();
    for (const tx of props.transactions.data ?? []) {
        const key = tx.transacted_at?.slice(0, 10) ?? 'unknown';
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(tx);
    }
    return [...map.entries()].map(([date, txs]) => ({ date, txs, label: dayLabel(date), dayTotal: dayTotal(txs) }));
});

function dayLabel(dateStr) {
    if (!dateStr || dateStr === 'unknown') return '';
    const d = new Date(dateStr);
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);
    const sameDay = (a, b) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    if (sameDay(d, today)) return 'Hôm nay';
    if (sameDay(d, yesterday)) return 'Hôm qua';
    return formatDateVi(d);
}

function dayTotal(txs) {
    return txs.reduce((sum, tx) => {
        if (tx.type === 'income') return sum + tx.amount;
        if (tx.type === 'expense') return sum - tx.amount;
        return sum;
    }, 0);
}

function iconClass(tx) {
    if (tx.type === 'income') return 'bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400';
    if (tx.is_transfer) return 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400';
    if (tx.is_adjustment) return 'bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400';
    return 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400';
}

function deleteTx(tx) {
    const msg = tx.is_transfer
        ? 'Hủy cả lệnh chuyển ví (2 bút toán)? Số dư sẽ được hoàn lại.'
        : 'Xóa giao dịch này? Số dư ví sẽ được hoàn lại.';
    if (!confirm(msg)) return;
    router.delete(route('transactions.destroy', tx.id));
}
</script>

<template>
    <Head title="Giao dịch" />
    <AppLayout title="Giao dịch">
        <div class="md:flex md:items-center md:justify-between mb-6 gap-3">
            <h2 class="text-2xl font-bold text-content">Giao dịch</h2>
            <div class="mt-4 md:mt-0 flex flex-wrap gap-2">
                <Link :href="route('transactions.bulk')" class="inline-flex px-4 py-2 rounded-md border border-strong bg-surface text-sm font-medium text-content-secondary hover:bg-surface-hover">Ghi cả ngày</Link>
                <Link :href="route('transactions.create')" class="inline-flex px-4 py-2 rounded-md bg-primary-600 dark:bg-primary-500 text-white text-sm font-medium hover:bg-primary-700 dark:hover:bg-primary-600">+ Giao dịch mới</Link>
            </div>
        </div>

        <form class="mb-6 flex flex-wrap gap-3 items-end bg-surface p-4 rounded-lg shadow border border-subtle" @submit.prevent="applyFilters">
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-slate-500">Ví</label>
                <select v-model="filterForm.wallet_id" class="mt-1 rounded-md border border-strong bg-surface text-content p-2 text-sm">
                    <option value="">Tất cả</option>
                    <option v-for="w in wallets" :key="w.id" :value="String(w.id)">{{ w.name }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 dark:text-slate-500">Loại</label>
                <select v-model="filterForm.type" class="mt-1 rounded-md border border-strong bg-surface text-content p-2 text-sm">
                    <option value="">Tất cả</option>
                    <option v-for="(label, key) in TX_TYPES" :key="key" :value="key">{{ label }}</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-gray-800 dark:bg-slate-700 text-white text-sm rounded-md hover:bg-gray-900 dark:hover:bg-slate-600">Lọc</button>
        </form>

        <div class="space-y-6">
            <div v-for="group in grouped" :key="group.date" class="bg-surface shadow-sm rounded-2xl border border-subtle overflow-hidden">
                <div class="bg-app/50 px-4 py-3 border-b border-subtle flex justify-between items-center">
                    <span class="font-bold text-content">{{ group.label }}</span>
                    <span v-if="group.dayTotal !== 0" class="text-sm font-semibold" :class="group.dayTotal > 0 ? 'text-green-600 dark:text-green-400' : 'text-content'">
                        {{ group.dayTotal > 0 ? '+' : '' }}{{ formatMoney(Math.abs(group.dayTotal), false) }} ₫
                    </span>
                </div>
                <div class="divide-y divide-subtle">
                    <div v-for="tx in group.txs" :key="tx.id" class="p-4 flex items-center justify-between hover:bg-surface-hover transition-colors relative group">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0" :class="iconClass(tx)">
                                <svg v-if="tx.type === 'income'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m0-16l-4 4m4-4l4 4" /></svg>
                                <svg v-else-if="tx.is_transfer" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                                <svg v-else-if="tx.is_adjustment" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" /></svg>
                                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20V4m0 16l-4-4m4 4l4-4" /></svg>
                            </div>
                            <div>
                                <p class="font-semibold text-content text-sm">{{ tx.description }}</p>
                                <div class="flex items-center gap-1.5 mt-0.5 text-[11px] text-content-muted">
                                    <span>{{ tx.wallet?.name }}</span>
                                    <template v-if="tx.category"><span>·</span><span>{{ tx.category }}</span></template>
                                    <template v-if="tx.is_transfer && tx.wallet_transfer">
                                        <span>·</span>
                                        <span class="text-blue-600 dark:text-blue-400">
                                            {{ tx.type === 'expense' ? `→ ${tx.wallet_transfer.to_wallet_name}` : `← ${tx.wallet_transfer.from_wallet_name}` }}
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col items-end">
                            <p class="font-bold text-sm" :class="tx.display_color_class">{{ tx.signed_amount }} ₫</p>
                            <button
                                v-if="!tx.is_from_loan"
                                type="button"
                                class="opacity-0 group-hover:opacity-100 transition-opacity mt-1 text-[10px] text-red-500 hover:underline"
                                @click="deleteTx(tx)"
                            >Xóa</button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!grouped.length" class="bg-surface rounded-2xl border border-dashed border-strong p-8 text-center text-sm text-content-muted shadow-sm">
                Chưa có giao dịch nào.
            </div>
        </div>

        <nav v-if="transactions.links?.length > 3" class="mt-6 flex flex-wrap gap-1 justify-center">
            <Link
                v-for="(link, i) in transactions.links"
                :key="i"
                :href="link.url || '#'"
                class="px-3 py-1 text-sm rounded-md border"
                :class="link.active ? 'bg-primary-600 text-white border-primary-600' : (link.url ? 'bg-surface border-subtle text-content hover:bg-surface-hover' : 'opacity-40 pointer-events-none border-subtle text-content-muted')"
                v-html="link.label"
            />
        </nav>
    </AppLayout>
</template>
