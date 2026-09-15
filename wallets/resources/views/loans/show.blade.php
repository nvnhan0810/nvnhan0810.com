@extends('layouts.app')

@section('content')
<div class="bg-surface shadow overflow-hidden sm:rounded-lg mb-6">
    <div class="px-4 py-5 sm:px-6 flex justify-between items-center">
        <div>
            <h3 class="text-lg leading-6 font-medium text-content">
                Chi tiết khoản vay: {{ $loan->name }}
            </h3>
            <p class="mt-1 max-w-2xl text-sm text-content-muted">
                Thông tin chi tiết và lịch trả nợ.
            </p>
        </div>
        <a href="{{ route('loans.index') }}" class="text-primary-600 dark:text-primary-400 hover:text-primary-900 dark:hover:text-primary-300 text-sm font-medium">
            &larr; Quay lại Dashboard
        </a>
    </div>
    <div class="border-t border-default px-4 py-5 sm:p-0">
        <dl class="sm:divide-y sm:divide-default">
            <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-content-muted">Loại khoản nợ</dt>
                <dd class="mt-1 text-sm text-content sm:mt-0 sm:col-span-2">
                    @if($loan->type == 'bank') Vay Ngân hàng @elseif($loan->type == 'borrow') Mượn Nợ @else Cho Mượn @endif
                </dd>
            </div>
            <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-content-muted">Tổng số tiền gốc</dt>
                <dd class="mt-1 text-sm text-content sm:mt-0 sm:col-span-2">{{ number_format($loan->principal_amount, 0) }} ₫</dd>
            </div>
            <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-content-muted">Ngày bắt đầu</dt>
                <dd class="mt-1 text-sm text-content sm:mt-0 sm:col-span-2">{{ $loan->started_at->format('d/m/Y') }}</dd>
            </div>
            @if($loan->wallet)
            <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-content-muted">Ví liên kết</dt>
                <dd class="mt-1 text-sm text-content sm:mt-0 sm:col-span-2">{{ $loan->wallet->name }}</dd>
            </div>
            @endif
            @if($loan->type == 'bank')
            <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-content-muted">Lãi suất</dt>
                <dd class="mt-1 text-sm text-content sm:mt-0 sm:col-span-2">{{ $loan->interest_rate }}% / năm</dd>
            </div>
            <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-content-muted">Phương pháp tính lãi</dt>
                <dd class="mt-1 text-sm text-content sm:mt-0 sm:col-span-2 space-x-2">
                    @php $method = $loan->interest_calculation_method ?? 'monthly'; @endphp
                    @if($method === 'daily')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200">
                            Actual/365
                        </span>
                    @elseif($method === 'custom')
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 dark:bg-purple-900/50 text-purple-800 dark:text-purple-200">
                            Custom (nhập tay)
                        </span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-muted text-content-secondary">
                            Theo tháng cố định
                        </span>
                    @endif
                </dd>
            </div>
            <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-content-muted">Thời hạn vay</dt>
                <dd class="mt-1 text-sm text-content sm:mt-0 sm:col-span-2">{{ $loan->term_months }} tháng</dd>
            </div>
            <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-content-muted">Ngày thanh toán cố định</dt>
                <dd class="mt-1 text-sm text-content sm:mt-0 sm:col-span-2">
                    Ngày {{ $paymentDay ?? '—' }} hàng tháng
                </dd>
            </div>
            <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-content-muted">Gốc còn lại</dt>
                <dd class="mt-1 text-sm text-content sm:mt-0 sm:col-span-2">{{ number_format($loan->remaining_principal ?? 0, 0) }} ₫</dd>
            </div>
            @if(($loan->interest_calculation_method ?? 'monthly') === 'custom')
            <div class="py-4 sm:py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                <dt class="text-sm font-medium text-content-muted">Tổng trả hàng tháng</dt>
                <dd class="mt-1 text-sm text-content sm:mt-0 sm:col-span-2">{{ number_format($loan->monthly_payment, 0) }} ₫</dd>
            </div>
            @endif
            @endif
        </dl>
    </div>
</div>

@if($loan->type == 'bank')
<div class="bg-surface shadow overflow-hidden sm:rounded-lg">
    <div class="px-4 py-5 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-content">
            Lịch trả nợ dự kiến
        </h3>
    </div>
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                <div class="shadow overflow-hidden border-b border-default sm:rounded-lg">
                    <table class="min-w-full divide-y divide-default">
                        <thead class="bg-app">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-content-muted uppercase tracking-wider">Tháng</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-content-muted uppercase tracking-wider">Ngày trả</th>
                                @php $method = $loan->interest_calculation_method ?? 'monthly'; @endphp
                                @if($method === 'daily')
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-content-muted uppercase tracking-wider">Số ngày</th>
                                @endif
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-content-muted uppercase tracking-wider">Tổng trả</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-content-muted uppercase tracking-wider">Tiền gốc</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-content-muted uppercase tracking-wider">Tiền lãi</th>
                                @if($method === 'custom')
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-content-muted uppercase tracking-wider">Phí</th>
                                @endif
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-content-muted uppercase tracking-wider">Dư nợ còn lại</th>
                            </tr>
                        </thead>
                        <tbody class="bg-surface divide-y divide-default">
                            @foreach($timeline as $row)
                            @if($row['type'] === 'early')
                            @php $payment = $row['payment']; $item = $row['period']; @endphp
                            <tr class="bg-sky-50 dark:bg-sky-900/30">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-sky-800" colspan="{{ $method === 'daily' ? 2 : 1 }}">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-sky-100 dark:bg-sky-900/50 text-sky-800 dark:text-sky-200">TT trước</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-sky-900">
                                    {{ $payment->paid_at->format('d/m/Y') }}
                                </td>
                                @if($method === 'daily')<td class="px-6 py-4 text-sm text-sky-600">—</td>@endif
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-sky-900">
                                    {{ number_format($payment->amount, 0) }} ₫
                                </td>
                                <td class="px-6 py-4 text-sm text-sky-600" colspan="{{ $method === 'custom' ? 4 : 3 }}">
                                    <p>{{ $row['note'] }}</p>
                                    <p class="text-xs mt-1">Kỳ đến hạn {{ $row['period_due_date']->format('d/m/Y') }} · {{ $payment->note }}</p>
                                </td>
                            </tr>
                            @else
                            @php $item = $row['period']; @endphp
                            <tr class="{{ ($row['is_paid'] ?? false) ? 'bg-green-50 dark:bg-green-900/30' : '' }}">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-content-muted">
                                    {{ $item['month_index'] }}
                                    @if($row['is_paid'] ?? false)
                                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200">Đã trả</span>
                                    @elseif($row['period_payment'] ?? null)
                                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 dark:bg-amber-900/50 text-amber-800 dark:text-amber-200">Đã TT</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-content">
                                    {{ $row['period_due_date']->format('d/m/Y') }}
                                    <span class="block text-xs text-content-muted">Lịch: {{ $item['date']->format('d/m/Y') }}</span>
                                    @if($item['is_adjusted'])
                                        <span class="ml-1 text-xs text-orange-600">(dời từ {{ $item['theoretical_date']->format('d/m') }})</span>
                                    @endif
                                </td>
                                @if($method === 'daily')
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-content-muted">{{ $item['days'] }} ngày</td>
                                @endif
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-content">{{ number_format($item['payment'], 0) }} ₫</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-content-muted">{{ number_format($item['principal'], 0) }} ₫</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-content-muted">{{ number_format($item['interest'], 0) }} ₫</td>
                                @if($method === 'custom')
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-content-muted">{{ number_format($item['fee'] ?? 0, 0) }} ₫</td>
                                @endif
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-content-muted">{{ number_format($item['remaining_principal'], 0) }} ₫</td>
                            </tr>
                            @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

@if($loan->type == 'bank' && $loan->payments->isNotEmpty())
<div class="bg-surface shadow overflow-hidden sm:rounded-lg mt-6">
    <div class="px-4 py-5 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-content">Lịch sử thanh toán</h3>
    </div>
    <ul class="divide-y divide-default border-t border-default">
        @foreach($loan->payments->sortByDesc('paid_at') as $payment)
        <li class="px-4 py-4 sm:px-6">
            <div class="flex justify-between items-start gap-2">
                <div>
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $payment->isEarly() ? 'bg-sky-100 dark:bg-sky-900/50 text-sky-800 dark:text-sky-200' : 'bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200' }}">{{ $payment->kindLabel() }}</span>
                    <p class="mt-1 font-medium text-content">{{ number_format($payment->amount, 0) }} ₫ · {{ $payment->paid_at->format('d/m/Y') }}</p>
                    @if($payment->period_due_date)
                        <p class="text-xs text-content-muted">Kỳ đến hạn {{ $payment->period_due_date->format('d/m/Y') }}</p>
                    @endif
                    <p class="text-sm text-gray-600 dark:text-slate-500">{{ $payment->note ?? '—' }}</p>
                </div>
                @if($payment->transaction)
                    <a href="{{ route('transactions.index', ['wallet_id' => $payment->transaction->wallet_id]) }}" class="text-xs text-primary-600 dark:text-primary-400 hover:underline shrink-0">Ví</a>
                @endif
            </div>
        </li>
        @endforeach
    </ul>
</div>
@endif

@if($loan->type != 'bank')
<div class="bg-surface shadow overflow-hidden sm:rounded-lg mt-6">
    <div class="px-4 py-5 sm:px-6">
        <h3 class="text-lg leading-6 font-medium text-content">
            Lịch sử thanh toán
        </h3>
    </div>
    <div class="border-t border-default">
        <ul role="list" class="divide-y divide-default">
            @forelse($loan->payments as $payment)
            <li class="px-4 py-4 sm:px-6">
                <div class="flex items-center justify-between">
                    <div class="text-sm font-medium text-primary-600 dark:text-primary-400 truncate">
                        {{ number_format($payment->amount, 0) }} ₫
                    </div>
                    <div class="ml-2 flex-shrink-0 flex">
                        <p class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200">
                            {{ $payment->paid_at->format('d/m/Y') }}
                        </p>
                    </div>
                </div>
                <div class="mt-2 sm:flex sm:justify-between">
                    <div class="sm:flex">
                        <p class="flex items-center text-sm text-content-muted">
                            {{ $payment->note ?? 'Không có ghi chú' }}
                            @if($payment->transaction)
                                · <a href="{{ route('transactions.index', ['wallet_id' => $payment->transaction->wallet_id]) }}" class="text-primary-600 dark:text-primary-400 hover:underline">Xem trên ví</a>
                            @endif
                        </p>
                    </div>
                </div>
            </li>
            @empty
            <li class="px-4 py-4 sm:px-6 text-sm text-content-muted text-center">
                Chưa có lịch sử thanh toán nào.
            </li>
            @endforelse
        </ul>
    </div>
</div>
@endif
@endsection

