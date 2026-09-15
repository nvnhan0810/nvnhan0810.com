@extends('layouts.app')

@section('content')
@include('partials.flash')

    <div class="mb-6">
        <h2 class="text-2xl font-bold leading-7 text-content sm:text-3xl sm:truncate">
            Kế hoạch tài chính
        </h2>
    </div>

    <div class="flex border-b border-subtle mb-6">
        <a href="{{ route('loans.index') }}" class="px-4 py-2 border-b-2 border-transparent text-content-muted hover:text-content font-medium text-sm transition-colors">Khoản vay & Nợ</a>
        <a href="{{ route('recurring-items.index') }}" class="px-4 py-2 border-b-2 border-primary-600 dark:border-primary-400 text-primary-600 dark:text-primary-400 font-semibold text-sm">Thu chi cố định</a>
    </div>

    <p class="mb-6 text-sm text-content-muted">Đặt ngày trong tháng và khoảng hiệu lực. Nhắc sẽ hiện trên tổng quan khi gần đến hạn; chọn ví lúc ghi giao dịch thực tế.</p>

<div class="bg-surface shadow rounded-lg p-6 mb-8">
    <h3 class="text-lg font-medium text-content mb-4">Thêm khoản mới</h3>
    <form action="{{ route('recurring-items.store') }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-content-secondary">Tên</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-content-secondary">Loại</label>
            <select name="type" required class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
                <option value="expense">Chi cố định</option>
                <option value="income">Thu cố định</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-content-secondary">Số tiền (₫)</label>
            <x-money-input name="amount" :value="old('amount')" required class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2" />
        </div>
        <div>
            <label class="block text-sm font-medium text-content-secondary">Ngày trong tháng</label>
            <input type="number" name="day_of_month" value="{{ old('day_of_month', 1) }}" min="1" max="31" required class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-content-secondary">Bắt đầu từ <span class="text-gray-400">(tùy chọn)</span></label>
            <input type="date" name="effective_from" value="{{ old('effective_from') }}" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-content-secondary">Kết thúc <span class="text-gray-400">(tùy chọn)</span></label>
            <input type="date" name="ends_at" value="{{ old('ends_at') }}" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-content-secondary">Ghi chú</label>
            <input type="text" name="note" value="{{ old('note') }}" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2">
        </div>
        <div class="sm:col-span-2 lg:col-span-3">
            <button type="submit" class="px-4 py-2 bg-primary-600 dark:bg-primary-500 text-white rounded-md text-sm font-medium hover:bg-primary-700 dark:hover:bg-primary-600">Thêm</button>
        </div>
    </form>
</div>

<div class="space-y-4">
    @forelse($items as $item)
    <div class="bg-surface rounded-lg shadow border border-default p-5">
        <form action="{{ route('recurring-items.update', $item) }}" method="POST" class="grid grid-cols-1 lg:grid-cols-5 gap-3 items-end">
            @csrf @method('PUT')
            <div class="lg:col-span-2">
                <label class="text-xs text-content-muted">Tên</label>
                <input type="text" name="name" value="{{ $item->name }}" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-content-muted">Loại</label>
                <select name="type" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2 text-sm">
                    <option value="expense" @selected($item->type === 'expense')>Chi</option>
                    <option value="income" @selected($item->type === 'income')>Thu</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-content-muted">Số tiền</label>
                <x-money-input name="amount" :value="$item->amount" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2 text-sm" />
            </div>
            <div>
                <label class="text-xs text-content-muted">Ngày</label>
                <input type="number" name="day_of_month" value="{{ $item->day_of_month }}" min="1" max="31" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-content-muted">Bắt đầu</label>
                <input type="date" name="effective_from" value="{{ optional($item->effective_from)->toDateString() }}" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-content-muted">Kết thúc</label>
                <input type="date" name="ends_at" value="{{ optional($item->ends_at)->toDateString() }}" class="mt-1 w-full rounded-md border border-strong bg-surface text-content p-2 text-sm">
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-slate-500">
                    <input type="checkbox" name="is_active" value="1" @checked($item->is_active) class="rounded text-primary-600 dark:text-primary-400"> Bật
                </label>
                <button type="submit" class="px-3 py-1.5 bg-primary-600 dark:bg-primary-500 text-white text-xs rounded-md">Lưu</button>
            </div>
        </form>
        <p class="mt-2 text-xs text-content-muted">
            Kỳ tới: {{ $item->next_due->format('d/m/Y') }} (còn {{ $item->days_until }} ngày)
        </p>
        <form action="{{ route('recurring-items.destroy', $item) }}" method="POST" class="mt-2" onsubmit="return confirm('Xóa khoản này?');">
            @csrf @method('DELETE')
            <button type="submit" class="text-xs text-red-600 dark:text-red-400 hover:underline">Xóa</button>
        </form>
    </div>
    @empty
    <p class="text-center text-content-muted py-8">Chưa có khoản thu/chi cố định.</p>
    @endforelse
</div>
@endsection
