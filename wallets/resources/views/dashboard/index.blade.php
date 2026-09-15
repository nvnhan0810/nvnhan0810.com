@extends('layouts.app')

@section('content')
@include('partials.flash')

<div x-data="{ showBalance: localStorage.getItem('showBalance') !== 'false' }" x-init="$watch('showBalance', val => localStorage.setItem('showBalance', val))" class="mb-8 pt-2">
    <div class="flex flex-col items-center justify-center text-center">
        <p class="text-sm font-medium text-content-muted mb-1 flex items-center gap-2">
            Tổng số dư
            <button @click="showBalance = !showBalance" class="text-content-muted hover:text-content transition-colors">
                <svg x-show="showBalance" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                <svg x-show="!showBalance" x-cloak class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.978 9.978 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
            </button>
        </p>
        <h1 class="text-4xl sm:text-5xl font-bold text-content tracking-tight">
            <span x-show="showBalance">{{ number_format($totalBalance ?? 0, 0) }} <span class="text-2xl opacity-70">₫</span></span>
            <span x-show="!showBalance" x-cloak>******</span>
        </h1>
    </div>

    {{-- Quick Actions --}}
    <div class="flex justify-center gap-6 mt-8">
        <a href="{{ route('transactions.create') }}" class="flex flex-col items-center gap-2 group">
            <div class="w-14 h-14 rounded-full bg-primary-50 dark:bg-primary-900/30 text-primary-600 dark:text-primary-400 flex items-center justify-center group-hover:scale-105 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            </div>
            <span class="text-xs font-medium text-content-secondary">Thêm GD</span>
        </a>
        <a href="{{ route('transactions.create', ['type' => 'transfer']) }}" class="flex flex-col items-center gap-2 group">
            <div class="w-14 h-14 rounded-full bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center group-hover:scale-105 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path></svg>
            </div>
            <span class="text-xs font-medium text-content-secondary">Chuyển ví</span>
        </a>
        <a href="{{ route('transaction-templates.index') }}" class="flex flex-col items-center gap-2 group">
            <div class="w-14 h-14 rounded-full bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center group-hover:scale-105 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
            </div>
            <span class="text-xs font-medium text-content-secondary">Mẫu GD</span>
        </a>
        <a href="#report" class="flex flex-col items-center gap-2 group">
            <div class="w-14 h-14 rounded-full bg-orange-50 dark:bg-orange-900/30 text-orange-600 dark:text-orange-400 flex items-center justify-center group-hover:scale-105 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            </div>
            <span class="text-xs font-medium text-content-secondary">Báo cáo</span>
        </a>
    </div>
</div>

{{-- Ví của bạn (Cards) --}}
<div class="mb-8">
    <div class="flex items-center justify-between mb-4 px-1">
        <h3 class="text-lg font-bold text-content">Ví của bạn</h3>
        <a href="{{ route('wallets.index') }}" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">Quản lý ví</a>
    </div>
    
    <div class="flex overflow-x-auto snap-x snap-mandatory gap-4 pb-4 -mx-4 px-4 sm:mx-0 sm:px-0 hide-scrollbar">
        @forelse($wallets as $wallet)
        <div class="snap-start shrink-0 w-72 h-44 rounded-2xl shadow-md p-5 flex flex-col justify-between transition-transform hover:scale-[1.02] active:scale-95 cursor-pointer relative overflow-hidden
            {{ $wallet->isCreditCard() ? 'bg-gradient-to-br from-indigo-600 to-purple-700 text-white' : 'bg-gradient-to-br from-slate-800 to-slate-900 text-white' }}"
            onclick="window.location.href='{{ route('wallets.index') }}'">
            
            {{-- Decorative circles --}}
            <div class="absolute -right-6 -top-6 w-24 h-24 rounded-full bg-white/10 blur-xl"></div>
            <div class="absolute -left-6 -bottom-6 w-32 h-32 rounded-full bg-white/5 blur-xl"></div>

            <div class="relative z-10 flex justify-between items-start">
                <div>
                    <p class="font-semibold text-lg tracking-wide shadow-sm">{{ $wallet->name }}</p>
                    <p class="text-xs opacity-80">{{ $wallet->typeLabel() }}</p>
                </div>
                <div class="w-8 h-8 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                </div>
            </div>
            
            <div class="relative z-10">
                @if($wallet->isCreditCard())
                    <p class="text-[10px] font-medium uppercase tracking-wider opacity-70 mb-1">Dư nợ</p>
                    <p class="font-bold text-2xl tracking-tight">{{ number_format($wallet->outstanding_balance ?? 0, 0) }} <span class="text-lg font-semibold opacity-80">₫</span></p>
                    <div class="mt-2 flex justify-between items-center text-xs opacity-90">
                        <span>Hạn mức: {{ number_format($wallet->credit_limit ?? 0, 0) }}</span>
                        <span>Còn: {{ number_format($wallet->spendableBalance(), 0) }}</span>
                    </div>
                @else
                    <p class="text-[10px] font-medium uppercase tracking-wider opacity-70 mb-1">Số dư</p>
                    <p class="font-bold text-2xl tracking-tight">{{ number_format($wallet->balance, 0) }} <span class="text-lg font-semibold opacity-80">₫</span></p>
                @endif
            </div>
        </div>
        @empty
        <div class="w-full bg-surface rounded-2xl border border-dashed border-strong p-8 text-center text-sm text-content-muted shadow-sm">
            Chưa có ví nào được ghim. <br><a href="{{ route('wallets.index') }}" class="text-primary-600 dark:text-primary-400 font-semibold hover:underline mt-2 inline-block">Ghim ví trên trang Ví</a>
        </div>
        @endforelse
    </div>
</div>

{{-- Reminders & Debt --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
    <div class="bg-surface rounded-2xl shadow-sm border border-subtle p-5 flex items-center justify-between">
        <div>
            <p class="text-[11px] font-medium uppercase tracking-wider text-content-muted">Tổng nợ</p>
            <p class="mt-1 text-2xl font-bold text-red-600 dark:text-red-400 tracking-tight">{{ number_format($totalDebt ?? 0, 0) }} <span class="text-lg font-semibold opacity-80">₫</span></p>
            <a href="{{ route('loans.index') }}" class="text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline mt-2 inline-block">Xem khoản vay →</a>
        </div>
        <div class="w-12 h-12 rounded-full bg-red-50 dark:bg-red-900/20 text-red-500 flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        </div>
    </div>
    <div class="bg-surface rounded-2xl shadow-sm border border-subtle p-5 flex items-center justify-between">
        <div>
            <p class="text-[11px] font-medium uppercase tracking-wider text-content-muted">Nhắc nhở thanh toán</p>
            <p class="mt-1 text-2xl font-bold tracking-tight {{ $upcomingReminders->where('insufficient_funds', true)->count() ? 'text-red-600 dark:text-red-400' : 'text-content' }}">{{ $upcomingReminders->count() }} <span class="text-lg font-medium opacity-70 text-content-muted">khoản sắp tới</span></p>
            <a href="#reminders" class="text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline mt-2 inline-block">Xem chi tiết →</a>
        </div>
        <div class="w-12 h-12 rounded-full bg-amber-50 dark:bg-amber-900/20 text-amber-500 flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
        </div>
    </div>
</div>

{{-- Cash Flow Report --}}
@php $cur = $cashFlowStats['current']; @endphp
<div id="report" class="mb-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 px-1">
        <div>
            <h3 class="text-lg font-bold text-content">Thu – Chi</h3>
            <span class="text-xs text-content-muted">{{ $cashFlowStats['range_label'] }}</span>
        </div>
        <div class="inline-flex rounded-xl border border-subtle bg-surface/50 p-1 shadow-sm overflow-x-auto hide-scrollbar">
            @foreach(\Wallets\Reporting\Application\TransactionAnalyticsService::PERIODS as $key => $meta)
                <a href="{{ route('dashboard', ['period' => $key]) }}"
                   class="px-4 py-1.5 rounded-lg whitespace-nowrap text-sm font-medium transition-colors {{ $chartPeriod === $key ? 'bg-surface shadow-sm border border-subtle text-primary-600 dark:text-primary-400' : 'text-content-muted hover:text-content' }}">
                    {{ $meta['label'] }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-3 gap-3 mb-5">
        <div class="bg-surface rounded-2xl shadow-sm border border-subtle p-4">
            <p class="text-[11px] font-medium uppercase tracking-wider text-content-muted mb-1">Thu vào</p>
            <p class="text-lg sm:text-xl font-bold text-green-600 dark:text-green-400 tracking-tight">{{ number_format($cur['income'] ?? 0, 0) }}</p>
        </div>
        <div class="bg-surface rounded-2xl shadow-sm border border-subtle p-4">
            <p class="text-[11px] font-medium uppercase tracking-wider text-content-muted mb-1">Chi ra</p>
            <p class="text-lg sm:text-xl font-bold text-red-600 dark:text-red-400 tracking-tight">{{ number_format($cur['expense'] ?? 0, 0) }}</p>
        </div>
        <div class="bg-surface rounded-2xl shadow-sm border border-subtle p-4">
            <p class="text-[11px] font-medium uppercase tracking-wider text-content-muted mb-1">Còn lại</p>
            <p class="text-lg sm:text-xl font-bold tracking-tight {{ ($cur['net'] ?? 0) >= 0 ? 'text-content' : 'text-red-600 dark:text-red-400' }}">{{ number_format($cur['net'] ?? 0, 0) }}</p>
        </div>
    </div>

    @if($cashFlowStats['has_data'])
    <div class="bg-surface rounded-2xl border border-subtle p-5 shadow-sm">
        <p class="text-sm font-semibold text-content mb-4">Thống kê {{ strtolower($cashFlowStats['period_label']) }}</p>
        <div class="h-56">
            <canvas id="chartSeries"></canvas>
        </div>
    </div>
    @endif
</div>

@if($cashFlowStats['has_data'])
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {
    const series = @json($cashFlowStats['series']);
    const fmt = (v) => new Intl.NumberFormat('vi-VN', { notation: 'compact', maximumFractionDigits: 1 }).format(v);

    Chart.defaults.color = document.documentElement.classList.contains('dark') ? '#9ca3af' : '#6b7280';
    Chart.defaults.borderColor = document.documentElement.classList.contains('dark') ? '#334155' : '#f3f4f6';
    Chart.defaults.font.family = "'Instrument Sans', ui-sans-serif, system-ui, sans-serif";

    new Chart(document.getElementById('chartSeries'), {
        type: 'bar',
        data: {
            labels: series.map(m => m.label),
            datasets: [
                { label: 'Thu', data: series.map(m => m.income), backgroundColor: '#22c55e', borderRadius: 6, maxBarThickness: 40 },
                { label: 'Chi', data: series.map(m => m.expense), backgroundColor: '#ef4444', borderRadius: 6, maxBarThickness: 40 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 12, weight: '500' }, usePointStyle: true } },
                tooltip: {
                    backgroundColor: document.documentElement.classList.contains('dark') ? '#1e293b' : '#ffffff',
                    titleColor: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#111827',
                    bodyColor: document.documentElement.classList.contains('dark') ? '#cbd5e1' : '#4b5563',
                    borderColor: document.documentElement.classList.contains('dark') ? '#334155' : '#e5e7eb',
                    borderWidth: 1,
                    padding: 10,
                    boxPadding: 4,
                    cornerRadius: 8,
                    callbacks: {
                        title: (items) => series[items[0].dataIndex]?.label_full || items[0].label,
                        label: (ctx) => ctx.dataset.label + ': ' + new Intl.NumberFormat('vi-VN').format(ctx.raw) + ' ₫'
                    }
                }
            },
            scales: {
                y: { beginAtZero: true, border: { display: false }, ticks: { callback: (v) => fmt(v) } },
                x: { border: { display: false }, grid: { display: false } }
            }
        }
    });
})();
</script>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 pb-6">
    <div>
        <div class="flex items-center justify-between mb-4 px-1">
            <h3 class="text-lg font-bold text-content">Giao dịch gần đây</h3>
            <a href="{{ route('transactions.index') }}" class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:underline">Tất cả</a>
        </div>
        <div class="bg-surface shadow-sm rounded-2xl border border-subtle overflow-hidden">
            @forelse($recentTransactions as $tx)
            <div class="block px-5 py-4 border-b border-subtle last:border-0">
                <div class="flex justify-between items-center gap-4">
                    <div class="flex items-center gap-4 overflow-hidden">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 {{ $tx->type === 'income' ? 'bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400' : 'bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400' }}">
                            @if($tx->type === 'income')
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m0-16l-4 4m4-4l4 4"></path></svg>
                            @else
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 20V4m0 16l-4-4m4 4l4-4"></path></svg>
                            @endif
                        </div>
                        <div class="overflow-hidden">
                            <p class="font-semibold text-content text-sm line-clamp-1">{{ $tx->description }}</p>
                            <p class="text-[11px] text-content-muted mt-0.5">{{ $tx->wallet->name }} · {{ $tx->transacted_at->format('d/m/Y') }}</p>
                        </div>
                    </div>
                    <p class="font-bold text-sm shrink-0 {{ $tx->type === 'income' ? 'text-green-600 dark:text-green-400' : 'text-content' }}">
                        {{ $tx->type === 'income' ? '+' : '-' }}{{ number_format($tx->amount, 0) }} ₫
                    </p>
                </div>
            </div>
            @empty
            <div class="px-5 py-8 text-sm text-content-muted text-center flex flex-col items-center">
                <svg class="w-10 h-10 text-gray-300 dark:text-slate-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Chưa có giao dịch gần đây.
            </div>
            @endforelse
        </div>
    </div>
    
    @if($upcomingReminders->isNotEmpty())
    <div id="reminders">
        <h3 class="text-lg font-bold text-content mb-4 px-1">Sắp đến hạn <span class="text-sm font-normal text-content-muted">({{ $alertDays }} ngày)</span></h3>
        <div class="space-y-3">
            @foreach($upcomingReminders as $reminder)
            <div class="rounded-2xl border p-5 flex flex-col gap-3 {{ $reminder->insufficient_funds ? 'bg-red-50 dark:bg-red-900/10 border-red-200 dark:border-red-900/50 shadow-sm' : ($reminder->kind === 'loan' ? 'bg-primary-50/50 dark:bg-primary-900/10 border-primary-200 dark:border-primary-900/50' : 'bg-surface border-subtle shadow-sm') }}">
                <div>
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <span class="font-bold text-base {{ $reminder->insufficient_funds ? 'text-red-900 dark:text-red-200' : 'text-content' }}">{{ $reminder->name }}</span>
                        <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-md {{ $reminder->type_badge_class }}">{{ $reminder->type_label }}</span>
                        @if($reminder->insufficient_funds)
                            <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-md bg-red-600 dark:bg-red-500 text-white animate-pulse">Thiếu tiền!</span>
                        @endif
                    </div>
                    <p class="text-sm font-medium {{ $reminder->insufficient_funds ? 'text-red-700 dark:text-red-400' : 'text-content-secondary' }}">
                        {{ number_format($reminder->amount, 0) }} ₫
                        @if($reminder->wallet)
                            · <span class="opacity-80">{{ $reminder->wallet->name }}</span>
                        @endif
                    </p>
                    <p class="text-xs text-content-muted mt-1">Đến hạn: {{ $reminder->due_date->format('d/m/Y') }}</p>
                </div>
                <div class="flex justify-end mt-1">
                    @if($reminder->kind === 'loan')
                        <a href="{{ $reminder->pay_url }}" class="text-sm font-semibold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30 px-4 py-2 rounded-lg hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors">Thanh toán ngay</a>
                    @else
                        <a href="{{ $reminder->pay_url }}" class="text-sm font-semibold text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/30 px-4 py-2 rounded-lg hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors">Ghi giao dịch</a>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
