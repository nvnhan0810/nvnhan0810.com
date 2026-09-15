@extends('layouts.app')

@section('content')
@include('partials.flash')

<div class="mb-6">
    <h2 class="text-2xl font-bold text-content">Mẫu giao dịch</h2>
    <p class="mt-1 text-sm text-content-muted">Thu, chi, cân đối số dư hoặc chuyển/rút giữa các ví.</p>
</div>

<div class="bg-surface shadow rounded-lg p-6 mb-8" x-data="{ type: 'expense' }">
    <h3 class="text-lg font-medium mb-4">Tạo mẫu</h3>
    <form action="{{ route('transaction-templates.store') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-content-secondary">Tên mẫu</label>
            <input type="text" name="name" required class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-content-secondary">Loại</label>
            <select name="type" x-model="type" required class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
                <option value="expense">Chi</option>
                <option value="income">Thu</option>
                <option value="adjustment">Cân đối</option>
                <option value="transfer">Chuyển / Rút ví</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-content-secondary">Số tiền (₫)</label>
            <x-money-input name="amount" required class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2" />
        </div>
        <div>
            <label class="block text-sm font-medium text-content-secondary">Mô tả mặc định</label>
            <input type="text" name="description" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
        </div>

        <div x-show="type === 'income' || type === 'expense'" class="contents">
            <div>
                <label class="block text-sm font-medium text-content-secondary">Danh mục</label>
                <input type="text" name="category" :disabled="type !== 'income' && type !== 'expense'" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-content-secondary">Ví mặc định</label>
                <select name="default_wallet_id" :disabled="type !== 'income' && type !== 'expense'" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
                    <option value="">—</option>
                    @foreach($wallets as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div x-show="type === 'adjustment'" x-cloak class="contents">
            <div>
                <label class="block text-sm font-medium text-content-secondary">Ví</label>
                <select name="default_wallet_id" :disabled="type !== 'adjustment'" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
                    @foreach($wallets as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-content-secondary">Hướng cân đối</label>
                <select name="adjustment_direction" :disabled="type !== 'adjustment'" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
                    <option value="increase">Tăng số dư</option>
                    <option value="decrease">Giảm số dư</option>
                </select>
            </div>
        </div>

        <div x-show="type === 'transfer'" x-cloak class="contents">
            <div>
                <label class="block text-sm font-medium text-content-secondary">Từ ví</label>
                <select name="from_wallet_id" :disabled="type !== 'transfer'" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
                    @foreach($wallets as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-content-secondary">Đến ví</label>
                <select name="to_wallet_id" :disabled="type !== 'transfer'" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
                    @foreach($wallets as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-content-secondary">Phí (₫)</label>
                <x-money-input name="fee" value="0" x-bind:disabled="type !== 'transfer'" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2" />
            </div>
        </div>

        <div class="sm:col-span-2 lg:col-span-3">
            <button type="submit" class="px-4 py-2 bg-primary-600 dark:bg-primary-500 text-white rounded-md text-sm">Thêm mẫu</button>
        </div>
    </form>
</div>

<div class="bg-surface shadow rounded-lg divide-y divide-subtle">
    @forelse($templates as $t)
    <div class="px-4 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <p class="font-medium text-content">{{ $t->name }}</p>
            <p class="text-sm text-content-muted">
                {{ $t->typeLabel() }} · {{ number_format($t->amount, 0) }} ₫
                @if($t->type === 'transfer' && (float) $t->fee > 0)
                    · phí {{ number_format($t->fee, 0) }} ₫
                @endif
                @if($t->description) · {{ $t->description }} @endif
                @if($t->defaultWallet) · Ví: {{ $t->defaultWallet->name }} @endif
                @if($t->fromWallet && $t->toWallet)
                    · {{ $t->fromWallet->name }} → {{ $t->toWallet->name }}
                @endif
                @if($t->type === 'adjustment' && $t->adjustment_direction)
                    · {{ \App\Models\Transaction::ADJUSTMENT_DIRECTIONS[$t->adjustment_direction] ?? $t->adjustment_direction }}
                @endif
            </p>
        </div>
        <div class="flex gap-3 text-sm">
            <a href="{{ route('transactions.create', ['template_id' => $t->id]) }}" class="text-primary-600 dark:text-primary-400 hover:underline">Dùng mẫu</a>
            <form action="{{ route('transaction-templates.destroy', $t) }}" method="POST" onsubmit="return confirm('Xóa mẫu?');">
                @csrf @method('DELETE')
                <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">Xóa</button>
            </form>
        </div>
    </div>
    @empty
    <p class="px-4 py-8 text-center text-content-muted">Chưa có mẫu. Tạo ở đây hoặc lưu từ form giao dịch.</p>
    @endforelse
</div>
@endsection
