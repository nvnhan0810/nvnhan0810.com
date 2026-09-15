<div class="wallet-card group relative bg-surface rounded-lg shadow border {{ $wallet->is_active ? ($wallet->isCreditCard() ? 'border-purple-200' : 'border-default') : 'border-strong bg-surface text-content opacity-60' }} p-5 select-none touch-manipulation"
     data-id="{{ $wallet->id }}"
     data-pinned="{{ !empty($pinned) ? '1' : '0' }}">
    <button type="button" class="wallet-handle absolute top-3 right-3 p-1.5 rounded-md text-content-muted/40 hover:text-content-muted hover:bg-surface-hover cursor-grab active:cursor-grabbing transition-colors" title="Kéo để sắp xếp" aria-label="Kéo để sắp xếp">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path d="M7 4a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm8-12a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0zm0 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
        </svg>
    </button>

    <div class="flex justify-between items-start gap-3 pr-8">
        <div>
            <h3 class="text-lg font-semibold text-content">{{ $wallet->name }}</h3>
            <span class="inline-flex mt-1 text-xs px-2 py-0.5 rounded-full {{ $wallet->isCreditCard() ? 'bg-purple-100 dark:bg-purple-900/50 text-purple-800 dark:text-purple-200' : 'bg-muted text-content-secondary' }}">{{ $wallet->typeLabel() }}</span>
            <button type="button"
                    class="wallet-pin-badge ml-1 inline-flex mt-1 text-xs px-2 py-0.5 rounded-full bg-muted text-content-muted transition-all duration-300 align-middle"
                    title="{{ !empty($pinned) ? 'Đã ghim · bấm để bỏ ghim' : 'Bấm để ghim lên Dashboard' }}"
                    aria-pressed="{{ !empty($pinned) ? 'true' : 'false' }}">
                📌 Pinned
            </button>
            @unless($wallet->is_active)
                <span class="ml-1 text-xs text-content-muted">(đã tắt)</span>
            @endunless
        </div>
        @include('wallets._card-summary', ['wallet' => $wallet])
    </div>
    @if($wallet->notes)
        <p class="mt-3 text-sm text-content-muted">{{ $wallet->notes }}</p>
    @endif
    <p class="mt-2 text-xs text-gray-400">{{ $wallet->transactions_count }} giao dịch</p>
    <div class="mt-4 flex gap-3 text-sm">
        <a href="{{ route('wallets.edit', $wallet) }}" class="text-primary-600 dark:text-primary-400 hover:underline">Sửa</a>
        <form action="{{ route('wallets.destroy', $wallet) }}" method="POST" onsubmit="return confirm('Xóa ví này?');">
            @csrf @method('DELETE')
            <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">Xóa</button>
        </form>
    </div>
</div>
