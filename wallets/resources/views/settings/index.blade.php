@extends('layouts.app')

@section('content')
@include('partials.flash')

<div class="max-w-xl mx-auto pb-6">
    <div class="flex items-center gap-4 mb-8 p-4 bg-surface rounded-2xl shadow-sm border border-subtle">
        <div class="w-16 h-16 rounded-full bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center text-primary-600 dark:text-primary-400 font-bold text-2xl">
            N
        </div>
        <div>
            <h2 class="text-xl font-bold text-content">Nhân</h2>
            <p class="text-sm text-content-muted">Quản lý tài chính cá nhân</p>
        </div>
    </div>

    <div class="space-y-4 mb-8">
        <h3 class="text-sm font-semibold text-content-muted uppercase tracking-wider px-2">Tính năng</h3>
        
        <div class="bg-surface rounded-2xl shadow-sm border border-subtle overflow-hidden">
            <a href="{{ route('wallets.index') }}" class="flex items-center gap-4 p-4 border-b border-subtle hover:bg-surface-hover transition-colors">
                <div class="w-10 h-10 rounded-full bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-content">Quản lý ví</p>
                    <p class="text-xs text-content-muted">Thêm, sửa, xóa, sắp xếp ví</p>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </a>
            
            <a href="{{ route('transaction-templates.index') }}" class="flex items-center gap-4 p-4 border-b border-subtle hover:bg-surface-hover transition-colors">
                <div class="w-10 h-10 rounded-full bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-content">Mẫu giao dịch</p>
                    <p class="text-xs text-content-muted">Tạo các mẫu giao dịch thường xuyên</p>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </a>
            
            <a href="{{ route('holidays.index') }}" class="flex items-center gap-4 p-4 hover:bg-surface-hover transition-colors">
                <div class="w-10 h-10 rounded-full bg-orange-50 dark:bg-orange-900/20 text-orange-600 dark:text-orange-400 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-content">Ngày lễ</p>
                    <p class="text-xs text-content-muted">Tự động dời lịch thu chi cố định</p>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            </a>
        </div>
    </div>

    <div class="space-y-4 mb-8">
        <h3 class="text-sm font-semibold text-content-muted uppercase tracking-wider px-2">Cấu hình hệ thống</h3>
        
        <form action="{{ route('settings.update') }}" method="POST" class="bg-surface shadow-sm rounded-2xl border border-subtle p-5 space-y-5">
            @csrf
            <div>
                <label class="block text-sm font-medium text-content">Nhắc thu/chi cố định trước bao nhiêu ngày?</label>
                <p class="text-[11px] text-content-muted mt-1">Các khoản có ngày đến hạn trong khoảng này sẽ hiện trên trang Tổng quan. Khoản chi mà ví không đủ tiền sẽ báo đỏ.</p>
                <div class="flex items-center gap-2 mt-2">
                    <input type="number" name="recurring_alert_days" value="{{ old('recurring_alert_days', $recurringAlertDays) }}" min="1" max="30" required class="w-24 rounded-lg border border-strong bg-surface text-content p-2 text-center font-semibold">
                    <span class="text-sm text-content-secondary">ngày</span>
                </div>
            </div>

            <div class="border-t border-subtle pt-5">
                <h4 class="text-sm font-medium text-content">Nhắc qua Telegram</h4>
                <p class="text-[11px] text-content-muted mt-1">Khi kỳ trả tới hạn mà chưa thanh toán, hệ thống gửi nhắc kèm link tạo khoản trả.</p>
                
                <label class="flex items-center gap-3 text-sm text-content-secondary mt-3 p-3 rounded-xl border border-subtle bg-app/50 cursor-pointer hover:bg-surface-hover transition-colors">
                    <input type="checkbox" name="telegram_enabled" value="1" @checked(old('telegram_enabled', $telegramEnabled)) class="w-5 h-5 rounded text-primary-600 dark:text-primary-400 border-strong bg-surface">
                    <span class="font-medium">Bật nhắc qua Telegram</span>
                </label>
                
                <div class="mt-4">
                    <label class="block text-sm font-medium text-content-secondary mb-1">Telegram Chat ID</label>
                    <input type="text" name="telegram_chat_id" value="{{ old('telegram_chat_id', $telegramChatId) }}" placeholder="VD: 123456789" class="w-full rounded-lg border border-strong bg-surface text-content p-2.5 text-sm">
                    <p class="text-[11px] text-content-muted mt-1.5">Nhắn <code>/start</code> cho bot, rồi lấy chat id (VD qua <code>@userinfobot</code>).</p>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full py-3 bg-primary-600 dark:bg-primary-500 text-white rounded-xl text-sm font-bold hover:bg-primary-700 dark:hover:bg-primary-600 transition-colors shadow-sm">Lưu cấu hình</button>
            </div>
        </form>
    </div>

    <div class="px-2">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full py-3 bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 rounded-xl text-sm font-bold hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors">Đăng xuất</button>
        </form>
    </div>
</div>
@endsection
