@extends('layouts.app')

@section('content')
@include('partials.flash')

<div class="md:flex md:items-center md:justify-between mb-6">
    <div>
        <h2 class="text-2xl font-bold text-content">Giao dịch</h2>
    </div>
    <a href="{{ route('transactions.create') }}" class="mt-4 md:mt-0 inline-flex px-4 py-2 rounded-md bg-primary-600 dark:bg-primary-500 text-white text-sm font-medium hover:bg-primary-700 dark:hover:bg-primary-600">+ Giao dịch mới</a>
</div>

<form method="GET" class="mb-6 flex flex-wrap gap-3 items-end bg-surface p-4 rounded-lg shadow border border-subtle">
    <div>
        <label class="block text-xs font-medium text-gray-600 dark:text-slate-500">Ví</label>
        <select name="wallet_id" class="mt-1 rounded-md border border-strong bg-surface text-content p-2 text-sm">
            <option value="">Tất cả</option>
            @foreach($wallets as $w)
                <option value="{{ $w->id }}" @selected(request('wallet_id') == $w->id)>{{ $w->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 dark:text-slate-500">Loại</label>
        <select name="type" class="mt-1 rounded-md border border-strong bg-surface text-content p-2 text-sm">
            <option value="">Tất cả</option>
            <option value="income" @selected(request('type') === 'income')>Thu</option>
            <option value="expense" @selected(request('type') === 'expense')>Chi</option>
            <option value="adjustment" @selected(request('type') === 'adjustment')>Cân đối</option>
            <option value="transfer" @selected(request('type') === 'transfer')>Chuyển ví</option>
        </select>
    </div>
    <button type="submit" class="px-4 py-2 bg-gray-800 dark:bg-slate-700 text-white text-sm rounded-md hover:bg-gray-900 dark:hover:bg-slate-600">Lọc</button>
</form>

<div class="space-y-6">
    @php
        $groupedTransactions = $transactions->groupBy(function($tx) {
            return $tx->transacted_at->format('Y-m-d');
        });
    @endphp

    @forelse($groupedTransactions as $date => $txs)
        @php
            $dateObj = \Carbon\Carbon::parse($date);
            $isToday = $dateObj->isToday();
            $isYesterday = $dateObj->isYesterday();
            $dateLabel = $isToday ? 'Hôm nay' : ($isYesterday ? 'Hôm qua' : $dateObj->format('d/m/Y'));
            $dayOfWeek = $isToday || $isYesterday ? '' : $dateObj->translatedFormat('l');
            
            $dayTotal = $txs->sum(function($tx) {
                if ($tx->type === 'income') return $tx->amount;
                if ($tx->type === 'expense') return -$tx->amount;
                return 0; // transfer/adjustment not counted in day total
            });
        @endphp
        
        <div class="bg-surface shadow-sm rounded-2xl border border-subtle overflow-hidden">
            <div class="bg-app/50 px-4 py-3 border-b border-subtle flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-content">{{ $dateLabel }}</span>
                    @if($dayOfWeek)
                        <span class="text-xs text-content-muted">{{ $dayOfWeek }}</span>
                    @endif
                </div>
                @if($dayTotal != 0)
                    <span class="text-sm font-semibold {{ $dayTotal > 0 ? 'text-green-600 dark:text-green-400' : 'text-content' }}">
                        {{ $dayTotal > 0 ? '+' : '' }}{{ number_format($dayTotal, 0) }} ₫
                    </span>
                @endif
            </div>
            
            <div class="divide-y divide-subtle">
                @foreach($txs as $tx)
                <div class="p-4 flex items-center justify-between hover:bg-surface-hover transition-colors relative group">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 {{ $tx->type === 'income' ? 'bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400' : ($tx->isTransfer() ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400' : ($tx->isAdjustment() ? 'bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400' : 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400')) }}">
                            @if($tx->type === 'income')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m0-16l-4 4m4-4l4 4"></path></svg>
                            @elseif($tx->isTransfer())
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
                            @elseif($tx->isAdjustment())
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path></svg>
                            @else
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20V4m0 16l-4-4m4 4l4-4"></path></svg>
                            @endif
                        </div>
                        
                        <div>
                            <p class="font-semibold text-content text-sm">{{ $tx->description }}</p>
                            <div class="flex items-center gap-1.5 mt-0.5 text-[11px] text-content-muted">
                                <span>{{ $tx->wallet->name }}</span>
                                @if($tx->category)
                                    <span>·</span>
                                    <span>{{ $tx->category }}</span>
                                @endif
                                @if($tx->isTransfer() && $tx->walletTransfer)
                                    <span>·</span>
                                    <span class="text-blue-600 dark:text-blue-400">
                                        @if($tx->type === 'expense')
                                            → {{ $tx->walletTransfer->toWallet?->name }}
                                        @else
                                            ← {{ $tx->walletTransfer->fromWallet?->name }}
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex flex-col items-end">
                        <p class="font-bold text-sm {{ $tx->displayColorClass() }}">
                            {{ $tx->signedAmountForDisplay() }} ₫
                        </p>
                        @if(!$tx->isFromLoan())
                            <form action="{{ route('transactions.destroy', $tx) }}" method="POST" onsubmit="return confirm(@js($tx->isTransfer() ? 'Hủy cả lệnh chuyển ví (2 bút toán)? Số dư sẽ được hoàn lại.' : 'Xóa giao dịch này? Số dư ví sẽ được hoàn lại.'));" class="opacity-0 group-hover:opacity-100 transition-opacity mt-1">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-[10px] text-red-500 hover:underline">Xóa</button>
                            </form>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="bg-surface rounded-2xl border border-dashed border-strong p-8 text-center text-sm text-content-muted shadow-sm">
            <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-slate-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
            Chưa có giao dịch nào.
        </div>
    @endforelse
</div>
<div class="mt-6">{{ $transactions->links() }}</div>
@endsection
