@if($wallet->isCreditCard())
    <div class="text-right">
        <p class="text-lg font-bold text-red-600 dark:text-red-400">{{ number_format($wallet->outstanding_balance ?? 0, 0) }} ₫</p>
        <p class="text-xs text-red-500">Dư nợ</p>
    </div>
@else
    <p class="text-xl font-bold {{ (float) $wallet->balance < 0 ? 'text-red-600 dark:text-red-400' : 'text-primary-600 dark:text-primary-400' }}">{{ number_format($wallet->balance, 0) }} ₫</p>
@endif

@if($wallet->isCreditCard())
    <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-gray-600 dark:text-slate-500">
        <div>
            <span class="text-gray-400">Hạn mức</span>
            <p class="font-medium text-content-secondary">{{ number_format($wallet->credit_limit ?? 0, 0) }} ₫</p>
        </div>
        <div>
            <span class="text-gray-400">Còn lại</span>
            <p class="font-medium text-primary-700 dark:text-primary-300">{{ number_format($wallet->spendableBalance(), 0) }} ₫</p>
        </div>
        <div>
            <span class="text-gray-400">Sao kê</span>
            <p class="font-medium">Ngày {{ $wallet->statement_day }}</p>
        </div>
        <div>
            <span class="text-gray-400">Thanh toán</span>
            <p class="font-medium">Ngày {{ $wallet->payment_day }}</p>
        </div>
    </div>
@endif
