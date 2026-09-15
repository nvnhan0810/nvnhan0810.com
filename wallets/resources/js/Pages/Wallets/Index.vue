<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Sortable from 'sortablejs';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatMoney, WALLET_TYPES } from '@/domain';

const props = defineProps({
    wallets: { type: Array, default: () => [] },
    walletTypes: { type: Object, default: () => WALLET_TYPES },
});

const localWallets = ref([]);
const gridEl = ref(null);
const sortStatus = ref('');
const sortStatusVisible = ref(false);
let sortable = null;
let saveTimer = null;
let saving = false;
let statusTimer = null;

function syncFromProps() {
    localWallets.value = props.wallets.map((w) => ({ ...w, is_pinned: !!w.is_pinned }));
}

watch(() => props.wallets, syncFromProps, { deep: true });

function setStatus(text, fading = true) {
    sortStatus.value = text;
    sortStatusVisible.value = true;
    clearTimeout(statusTimer);
    if (fading) {
        statusTimer = setTimeout(() => {
            sortStatusVisible.value = false;
        }, 1600);
    }
}

function collectPayload() {
    return localWallets.value.map((w, order) => ({
        id: w.id,
        is_pinned: !!w.is_pinned,
        order,
    }));
}

function getCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function persist() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(async () => {
        if (saving) return;
        saving = true;
        setStatus('Đang lưu…', false);
        try {
            const res = await fetch(route('wallets.update-sort'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ wallets: collectPayload() }),
            });
            if (!res.ok) throw new Error('save failed');
            setStatus('Đã lưu');
        } catch {
            setStatus('Lưu thất bại');
        } finally {
            saving = false;
        }
    }, 180);
}

function normalizeOrder(preferFrontUnpinnedId = null) {
    const pinned = localWallets.value.filter((w) => w.is_pinned);
    let unpinned = localWallets.value.filter((w) => !w.is_pinned);
    if (preferFrontUnpinnedId) {
        const idx = unpinned.findIndex((w) => w.id === preferFrontUnpinnedId);
        if (idx >= 0) {
            const [front] = unpinned.splice(idx, 1);
            unpinned = [front, ...unpinned];
        }
    }
    localWallets.value = [...pinned, ...unpinned];
}

function inferPinned(walletId, newIndex) {
    const others = localWallets.value.filter((w) => w.id !== walletId);
    const otherPinned = others.filter((w) => w.is_pinned).length;
    if (otherPinned === 0) return newIndex === 0;
    return newIndex < otherPinned;
}

function applyPin(walletId, pinned) {
    const w = localWallets.value.find((x) => x.id === walletId);
    if (w) w.is_pinned = pinned;
}

function onDragEnd(evt) {
    const { oldIndex, newIndex, item } = evt;
    if (oldIndex === newIndex && oldIndex === undefined) return;

    const id = Number(item.dataset.id);
    const moved = localWallets.value.splice(oldIndex, 1)[0];
    localWallets.value.splice(newIndex, 0, moved);

    const wasPinned = moved.is_pinned;
    const nowPinned = inferPinned(id, newIndex);
    applyPin(id, nowPinned);

    const justUnpinned = wasPinned && !nowPinned;
    normalizeOrder(justUnpinned ? id : null);
    persist();
}

function togglePin(wallet) {
    const wasPinned = wallet.is_pinned;
    wallet.is_pinned = !wasPinned;
    normalizeOrder(wasPinned ? wallet.id : null);
    persist();
}

function deleteWallet(wallet) {
    if (!confirm('Xóa ví này?')) return;
    router.delete(route('wallets.destroy', wallet.id));
}

function initSortable() {
    sortable?.destroy();
    if (!gridEl.value || !localWallets.value.length) return;
    sortable = Sortable.create(gridEl.value, {
        animation: 240,
        easing: 'cubic-bezier(0.2, 0, 0, 1)',
        handle: '.wallet-handle',
        draggable: '.wallet-card',
        filter: 'a, button:not(.wallet-handle):not(.wallet-pin-badge), input, form, textarea',
        preventOnFilter: false,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        dragClass: 'sortable-drag',
        forceFallback: false,
        fallbackOnBody: true,
        swapThreshold: 0.65,
        delayOnTouchOnly: true,
        delay: 120,
        onEnd: onDragEnd,
    });
}

onMounted(() => {
    syncFromProps();
    nextTick(initSortable);
});

onBeforeUnmount(() => {
    sortable?.destroy();
    clearTimeout(saveTimer);
    clearTimeout(statusTimer);
});
</script>

<template>
    <Head title="Quản lý ví" />
    <AppLayout title="Quản lý ví">
        <div class="md:flex md:items-center md:justify-between mb-6 gap-4">
            <div>
                <h2 class="text-2xl font-bold text-content">Quản lý ví</h2>
                <p class="mt-1 text-sm text-content-muted">Kéo thả để sắp xếp · Thả vào giữa các ví đã ghim để ghim · Bấm 📌 để ghim/bỏ ghim</p>
            </div>
            <div class="mt-4 md:mt-0 flex items-center gap-3">
                <span
                    class="text-xs text-content-muted transition-opacity duration-300"
                    :class="sortStatusVisible ? 'opacity-100' : 'opacity-0'"
                    aria-live="polite"
                >{{ sortStatus }}</span>
                <Link :href="route('wallets.create')" class="inline-flex items-center px-4 py-2 rounded-md bg-primary-600 dark:bg-primary-500 text-white text-sm font-medium hover:bg-primary-700 dark:hover:bg-primary-600">+ Thêm ví</Link>
            </div>
        </div>

        <div v-if="!localWallets.length" class="text-center py-12 text-content-muted">
            Chưa có ví nào. <Link :href="route('wallets.create')" class="text-primary-600 dark:text-primary-400">Tạo ví đầu tiên</Link>
        </div>

        <div v-else ref="gridEl" class="wallet-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div
                v-for="wallet in localWallets"
                :key="wallet.id"
                class="wallet-card group relative bg-surface rounded-lg shadow border p-5 select-none touch-manipulation"
                :class="wallet.is_active ? (wallet.is_credit_card ? 'border-purple-200' : 'border-default') : 'border-strong opacity-60'"
                :data-id="wallet.id"
                :data-pinned="wallet.is_pinned ? '1' : '0'"
            >
                <button type="button" class="wallet-handle absolute top-3 right-3 p-1.5 rounded-md text-content-muted/40 hover:text-content-muted hover:bg-surface-hover cursor-grab active:cursor-grabbing transition-colors" title="Kéo để sắp xếp" aria-label="Kéo để sắp xếp">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M7 4a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm8-12a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0z" /></svg>
                </button>

                <div class="flex justify-between items-start gap-3 pr-8">
                    <div>
                        <h3 class="text-lg font-semibold text-content">{{ wallet.name }}</h3>
                        <span class="inline-flex mt-1 text-xs px-2 py-0.5 rounded-full" :class="wallet.is_credit_card ? 'bg-purple-100 dark:bg-purple-900/50 text-purple-800 dark:text-purple-200' : 'bg-muted text-content-secondary'">{{ wallet.type_label }}</span>
                        <button
                            type="button"
                            class="wallet-pin-badge ml-1 inline-flex mt-1 text-xs px-2 py-0.5 rounded-full bg-muted text-content-muted transition-all duration-300 align-middle"
                            :title="wallet.is_pinned ? 'Đã ghim · bấm để bỏ ghim' : 'Bấm để ghim lên Dashboard'"
                            :aria-pressed="wallet.is_pinned ? 'true' : 'false'"
                            @click.stop="togglePin(wallet)"
                        >📌 Pinned</button>
                        <span v-if="!wallet.is_active" class="ml-1 text-xs text-content-muted">(đã tắt)</span>
                    </div>
                    <div>
                        <template v-if="wallet.is_credit_card">
                            <div class="text-right">
                                <p class="text-lg font-bold text-red-600 dark:text-red-400">{{ formatMoney(wallet.outstanding_balance ?? 0, false) }} ₫</p>
                                <p class="text-xs text-red-500">Dư nợ</p>
                            </div>
                        </template>
                        <template v-else>
                            <p class="text-xl font-bold" :class="wallet.balance < 0 ? 'text-red-600 dark:text-red-400' : 'text-primary-600 dark:text-primary-400'">{{ formatMoney(wallet.balance, false) }} ₫</p>
                        </template>
                    </div>
                </div>

                <div v-if="wallet.is_credit_card" class="mt-3 grid grid-cols-2 gap-2 text-xs text-gray-600 dark:text-slate-500">
                    <div><span class="text-gray-400">Hạn mức</span><p class="font-medium text-content-secondary">{{ formatMoney(wallet.credit_limit ?? 0, false) }} ₫</p></div>
                    <div><span class="text-gray-400">Còn lại</span><p class="font-medium text-primary-700 dark:text-primary-300">{{ formatMoney(wallet.spendable_balance ?? 0, false) }} ₫</p></div>
                    <div><span class="text-gray-400">Sao kê</span><p class="font-medium">Ngày {{ wallet.statement_day }}</p></div>
                    <div><span class="text-gray-400">Thanh toán</span><p class="font-medium">Ngày {{ wallet.payment_day }}</p></div>
                </div>

                <p v-if="wallet.notes" class="mt-3 text-sm text-content-muted">{{ wallet.notes }}</p>
                <p class="mt-2 text-xs text-gray-400">{{ wallet.transactions_count ?? 0 }} giao dịch</p>
                <div class="mt-4 flex gap-3 text-sm">
                    <Link :href="route('wallets.edit', wallet.id)" class="text-primary-600 dark:text-primary-400 hover:underline">Sửa</Link>
                    <button type="button" class="text-red-600 dark:text-red-400 hover:underline" @click="deleteWallet(wallet)">Xóa</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.wallet-grid { grid-auto-flow: row; }
.wallet-card[data-pinned="1"] .wallet-pin-badge {
    opacity: 1;
    max-width: 8rem;
    background: rgb(219 234 254);
    color: rgb(30 64 175);
}
:global(.dark) .wallet-card[data-pinned="1"] .wallet-pin-badge {
    background: rgb(30 58 138 / 0.5);
    color: rgb(191 219 254);
}
.wallet-card[data-pinned="0"] .wallet-pin-badge {
    opacity: 0;
    max-width: 0;
    padding-left: 0;
    padding-right: 0;
    margin-left: 0;
    overflow: hidden;
}
.wallet-card[data-pinned="0"]:hover .wallet-pin-badge,
.wallet-card[data-pinned="0"]:focus-within .wallet-pin-badge {
    opacity: 1;
    max-width: 8rem;
    padding-left: 0.5rem;
    padding-right: 0.5rem;
    margin-left: 0.25rem;
}
.wallet-card { transition: transform 0.22s cubic-bezier(0.2, 0, 0, 1), box-shadow 0.22s ease, border-color 0.22s ease; }
:global(.wallet-card.sortable-ghost) { opacity: 0.35; transform: scale(0.98); }
:global(.wallet-card.sortable-chosen) { box-shadow: 0 12px 28px -8px rgb(0 0 0 / 0.25); z-index: 20; }
:global(.wallet-card.sortable-drag) {
    opacity: 1 !important;
    transform: scale(1.02) rotate(0.6deg);
    box-shadow: 0 18px 40px -12px rgb(79 70 229 / 0.35);
    border-color: rgb(99 102 241);
}
</style>
