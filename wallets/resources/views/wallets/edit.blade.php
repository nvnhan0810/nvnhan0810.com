@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto">
    <h2 class="text-2xl font-bold text-content mb-6">Sửa ví: {{ $wallet->name }}</h2>
    @include('partials.flash')
    <form action="{{ route('wallets.update', $wallet) }}" method="POST" class="bg-surface shadow rounded-lg p-6 space-y-4">
        @csrf @method('PUT')
        @include('wallets._form', ['wallet' => $wallet])
        <div class="flex gap-3 pt-2">
            <button type="submit" class="px-4 py-2 bg-primary-600 dark:bg-primary-500 text-white rounded-md text-sm font-medium hover:bg-primary-700 dark:hover:bg-primary-600">Cập nhật</button>
            <a href="{{ route('wallets.index') }}" class="px-4 py-2 border border-strong bg-surface text-content rounded-md text-sm text-content-secondary hover:bg-surface-hover">Hủy</a>
        </div>
    </form>
</div>
@endsection
