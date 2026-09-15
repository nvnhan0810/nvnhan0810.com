@extends('layouts.app')

@section('content')
@include('partials.flash')

<div class="md:flex md:items-center md:justify-between mb-6 gap-4">
    <div>
        <h2 class="text-2xl font-bold text-content">Quản lý ví</h2>
        <p class="mt-1 text-sm text-content-muted">Kéo thả để sắp xếp · Thả vào giữa các ví đã ghim để ghim · Bấm 📌 để ghim/bỏ ghim</p>
    </div>
    <div class="mt-4 md:mt-0 flex items-center gap-3">
        <span id="wallet-sort-status" class="text-xs text-content-muted transition-opacity duration-300 opacity-0" aria-live="polite"></span>
        <a href="{{ route('wallets.create') }}" class="inline-flex items-center px-4 py-2 rounded-md bg-primary-600 dark:bg-primary-500 text-white text-sm font-medium hover:bg-primary-700 dark:hover:bg-primary-600">+ Thêm ví</a>
    </div>
</div>

@if($wallets->isEmpty())
    <div class="text-center py-12 text-content-muted">
        Chưa có ví nào. <a href="{{ route('wallets.create') }}" class="text-primary-600 dark:text-primary-400">Tạo ví đầu tiên</a>
    </div>
@else
<div id="wallet-board"
     data-save-url="{{ route('wallets.update-sort') }}"
     data-csrf="{{ csrf_token() }}">
    <div id="wallet-grid" class="wallet-grid grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($wallets as $wallet)
            @include('wallets._board-card', ['wallet' => $wallet, 'pinned' => (bool) $wallet->is_pinned])
        @endforeach
    </div>
</div>
@endif

<style>
    .wallet-grid { grid-auto-flow: row; }
    .wallet-card[data-pinned="1"] .wallet-pin-badge {
        opacity: 1;
        max-width: 8rem;
        background: rgb(219 234 254);
        color: rgb(30 64 175);
    }
    .dark .wallet-card[data-pinned="1"] .wallet-pin-badge {
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
    .wallet-card {
        transition: transform 0.22s cubic-bezier(0.2, 0, 0, 1), box-shadow 0.22s ease, border-color 0.22s ease;
    }
    .wallet-card.sortable-ghost { opacity: 0.35; transform: scale(0.98); }
    .wallet-card.sortable-chosen { box-shadow: 0 12px 28px -8px rgb(0 0 0 / 0.25); z-index: 20; }
    .wallet-card.sortable-drag {
        opacity: 1 !important;
        transform: scale(1.02) rotate(0.6deg);
        box-shadow: 0 18px 40px -12px rgb(79 70 229 / 0.35);
        border-color: rgb(99 102 241);
    }
    .wallet-card.just-moved { animation: wallet-settle 0.45s cubic-bezier(0.2, 0, 0, 1); }
    @keyframes wallet-settle {
        0% { transform: scale(0.97); }
        55% { transform: scale(1.015); }
        100% { transform: scale(1); }
    }
    .wallet-card.just-pinned .wallet-pin-badge { animation: pin-pop 0.4s cubic-bezier(0.2, 0, 0, 1); }
    @keyframes pin-pop {
        0% { transform: scale(0.5); opacity: 0; }
        70% { transform: scale(1.15); }
        100% { transform: scale(1); opacity: 1; }
    }
</style>

@if($wallets->isNotEmpty())
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
(function () {
    const board = document.getElementById('wallet-board');
    const grid = document.getElementById('wallet-grid');
    if (!board || !grid || typeof Sortable === 'undefined') return;

    const statusEl = document.getElementById('wallet-sort-status');
    const saveUrl = board.dataset.saveUrl;
    const csrf = board.dataset.csrf;
    let saveTimer = null;
    let saving = false;

    const cards = () => Array.from(grid.querySelectorAll('.wallet-card'));
    const isPinned = (el) => el?.dataset.pinned === '1';

    function setStatus(text, fading = true) {
        statusEl.textContent = text;
        statusEl.classList.remove('opacity-0');
        statusEl.classList.add('opacity-100');
        if (fading) {
            clearTimeout(setStatus._t);
            setStatus._t = setTimeout(() => {
                statusEl.classList.add('opacity-0');
                statusEl.classList.remove('opacity-100');
            }, 1600);
        }
    }

    function collectPayload() {
        return cards().map((el, order) => ({
            id: Number(el.dataset.id),
            is_pinned: isPinned(el),
            order,
        }));
    }

    function persist() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(async () => {
            if (saving) return;
            saving = true;
            setStatus('Đang lưu…', false);
            try {
                const res = await fetch(saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ wallets: collectPayload() }),
                });
                if (!res.ok) throw new Error('save failed');
                setStatus('Đã lưu');
            } catch (e) {
                setStatus('Lưu thất bại');
            } finally {
                saving = false;
            }
        }, 180);
    }

    function pulse(el, becamePinned) {
        el.classList.remove('just-moved', 'just-pinned');
        void el.offsetWidth;
        el.classList.add('just-moved');
        if (becamePinned) el.classList.add('just-pinned');
        setTimeout(() => el.classList.remove('just-moved', 'just-pinned'), 500);
    }

    /** Keep pinned cards as a contiguous prefix (DOM order within each group preserved). */
    function normalizeOrder(preferFrontUnpinned = null) {
        const all = cards();
        const pinned = all.filter(isPinned);
        let unpinned = all.filter((c) => !isPinned(c));
        if (preferFrontUnpinned && unpinned.includes(preferFrontUnpinned)) {
            unpinned = [preferFrontUnpinned, ...unpinned.filter((c) => c !== preferFrontUnpinned)];
        }
        [...pinned, ...unpinned].forEach((el) => grid.appendChild(el));
    }

    /**
     * Pin if dropped inside the pinned prefix (e.g. between pinned cards).
     * newIndex < otherPinned → among pinned → pin.
     * Otherwise unpinned. Bootstrap: no pins yet → only index 0 pins.
     */
    function inferPinned(el, wasPinned, newIndex) {
        const all = cards();
        const otherPinned = all.filter((c) => c !== el && isPinned(c)).length;
        if (otherPinned === 0) {
            return newIndex === 0;
        }
        return newIndex < otherPinned;
    }

    function applyPin(el, pinned) {
        el.dataset.pinned = pinned ? '1' : '0';
        const badge = el.querySelector('.wallet-pin-badge');
        if (badge) {
            badge.title = pinned ? 'Đã ghim · bấm để bỏ ghim' : 'Bấm để ghim lên Dashboard';
            badge.setAttribute('aria-pressed', pinned ? 'true' : 'false');
        }
    }

    Sortable.create(grid, {
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
        onStart(evt) {
            evt.item._wasPinned = isPinned(evt.item);
        },
        onEnd(evt) {
            const el = evt.item;
            const wasPinned = !!el._wasPinned;
            const nowPinned = inferPinned(el, wasPinned, evt.newIndex);
            applyPin(el, nowPinned);

            const justUnpinned = wasPinned && !nowPinned;
            normalizeOrder(justUnpinned ? el : null);
            pulse(el, !wasPinned && nowPinned);
            persist();
        },
    });

    grid.addEventListener('click', (e) => {
        const badge = e.target.closest('.wallet-pin-badge');
        if (!badge || !grid.contains(badge)) return;
        e.preventDefault();
        e.stopPropagation();
        const el = badge.closest('.wallet-card');
        if (!el) return;
        const wasPinned = isPinned(el);
        applyPin(el, !wasPinned);
        normalizeOrder(wasPinned ? el : null);
        pulse(el, !wasPinned);
        persist();
    });
})();
</script>
@endif
@endsection
