@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto" x-data="loanForm()">
    <div class="md:grid md:grid-cols-3 md:gap-6">
        <div class="md:col-span-1">
            <div class="px-4 sm:px-0">
                <h3 class="text-lg font-medium leading-6 text-content">Tạo Khoản Vay / Mượn Mới</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-slate-500">
                    Nhập thông tin chi tiết về khoản nợ hoặc cho vay.
                </p>
            </div>
        </div>
        <div class="mt-5 md:mt-0 md:col-span-2">
            <form action="{{ route('loans.store') }}" method="POST" @submit="onSubmit($event)" x-ref="loanForm">
                @csrf
                <div class="shadow sm:rounded-md sm:overflow-hidden">
                    <div class="px-4 py-5 bg-surface space-y-6 sm:p-6">

                        <!-- Type Selection -->
                        <div class="col-span-6 sm:col-span-3">
                            <label for="type" class="block text-sm font-medium text-content-secondary">Loại khoản nợ</label>
                            <select id="type" name="type" x-model="type" class="mt-1 block w-full py-2 px-3 border border-strong bg-surface text-content rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
                                <option value="bank">Vay Ngân Hàng (Có lãi suất)</option>
                                <option value="borrow">Mượn Nợ (Cá nhân)</option>
                                <option value="lend">Cho Mượn (Tài sản)</option>
                            </select>
                        </div>

                        <!-- Common Fields -->
                        <div class="grid grid-cols-6 gap-6">
                            <div class="col-span-6 sm:col-span-4">
                                <label for="name" class="block text-sm font-medium text-content-secondary" x-text="type === 'bank' ? 'Tên Ngân hàng / Tổ chức' : 'Tên Người mượn / Cho mượn'"></label>
                                <input type="text" name="name" id="name" x-model="name" required class="mt-1 focus:ring-primary-500 focus:border-primary-500 block w-full shadow-sm sm:text-sm border-strong bg-surface text-content rounded-md border p-2">
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <label for="principal_amount" class="block text-sm font-medium text-content-secondary">Tổng số tiền (Gốc)</label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <x-money-input name="principal_amount" id="principal_amount" alpine-model="principal" required class="focus:ring-primary-500 focus:border-primary-500 block w-full pl-3 pr-12 sm:text-sm border-strong bg-surface text-content rounded-md p-2 border" />
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-content-muted sm:text-sm">VND</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-span-6 sm:col-span-3">
                                <label for="started_at" class="block text-sm font-medium text-content-secondary">Ngày bắt đầu</label>
                                <input type="text" name="started_at" id="started_at" x-model="startDate" @change="startDate = $event.target.value; syncMonthsPaid(true)" required class="datepicker mt-1 focus:ring-primary-500 focus:border-primary-500 block w-full shadow-sm sm:text-sm border-strong bg-surface text-content rounded-md border p-2" placeholder="dd/mm/yyyy">
                            </div>
                        </div>

                                                <div class="rounded-lg border border-primary-100 bg-primary-50/50 dark:bg-primary-900/50 p-4 space-y-3" x-show="startsToday()">
                            <p class="text-sm font-medium text-primary-900">Dòng tiền qua ví (ngày bắt đầu là hôm nay)</p>
                            <p class="text-xs text-primary-700 dark:text-primary-300" x-show="type === 'lend'">Cho mượn → chi tiền từ ví.</p>
                            <p class="text-xs text-primary-700 dark:text-primary-300" x-show="type !== 'lend'">Vay / mượn → thu tiền vào ví.</p>
                            <label class="flex items-center gap-2 text-sm text-content-secondary">
                                <input type="checkbox" name="record_cash_flow" value="1" x-model="recordCashFlow" class="rounded text-primary-600 dark:text-primary-400">
                                Ghi nhận giao dịch vào ví khi tạo
                            </label>
                            <div x-show="recordCashFlow" class="space-y-3">
                                <div>
                                    <label for="wallet_id" class="block text-sm font-medium text-content-secondary">Ví</label>
                                    <select name="wallet_id" id="wallet_id" x-model="walletId" :required="recordCashFlow && startsToday()" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2 text-sm">
                                        <option value="">Chọn ví</option>
                                        @foreach($wallets as $w)
                                            <option value="{{ $w->id }}" @selected(old('wallet_id') == $w->id)>{{ $w->name }}</option>
                                        @endforeach
                                    </select>
                                    @if($wallets->isEmpty())
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400"><a href="{{ route('wallets.create') }}" class="underline">Tạo ví</a> trước.</p>
                                    @endif
                                </div>
                                <div>
                                    <label for="received_amount" class="block text-sm font-medium text-content-secondary" x-text="type === 'lend' ? 'Số tiền thực chi' : 'Số tiền thực nhận vào ví'"></label>
                                    <input type="text" inputmode="numeric" name="received_amount" id="received_amount"
                                        class="money-input mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2 text-sm"
                                        x-init="$el.__moneyAlpine = true"
                                        :value="$money.format(receivedAmount)"
                                        @input="$money.onInput($event.target, v => { receivedAmount = v; receivedTouched = true; })">
                                    <p class="text-xs text-content-muted mt-1">Mặc định bằng số tiền gốc. Có thể sửa nếu số thực nhận khác (phí, giải ngân một phần…).</p>
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-content-muted" x-show="!startsToday()">Ngày bắt đầu không phải hôm nay → chỉ tạo khoản vay, không tạo giao dịch ví. Ghi nhận trả khi tới kỳ.</p>

                        <!-- Bank Specific Fields -->
                        <div x-show="type === 'bank'" class="border-t border-default pt-4 mt-4 grid grid-cols-6 gap-6">
                            <div class="col-span-6 sm:col-span-2">
                                <label for="interest_rate" class="block text-sm font-medium text-content-secondary">Lãi suất (%/năm)</label>
                                <input type="number" step="0.01" name="interest_rate" id="interest_rate" x-model.number="rate" class="mt-1 focus:ring-primary-500 focus:border-primary-500 block w-full shadow-sm sm:text-sm border-strong bg-surface text-content rounded-md border p-2">
                            </div>

                            <div class="col-span-6 sm:col-span-4">
                                <label for="interest_calculation_method" class="block text-sm font-medium text-content-secondary">Phương pháp tính lãi</label>
                                <select name="interest_calculation_method" id="interest_calculation_method" x-model="calculationMethod" class="mt-1 focus:ring-primary-500 focus:border-primary-500 block w-full shadow-sm sm:text-sm border-strong bg-surface text-content rounded-md border p-2">
                                    <option value="monthly">Lãi suất cố định theo tháng (Đơn giản)</option>
                                    <option value="daily">Tính theo ngày thực tế (Actual/365 - Ngân hàng)</option>
                                    <option value="custom">Tùy chỉnh thủ công từng tháng</option>
                                </select>
                                <p class="mt-1 text-xs text-content-muted space-y-0.5">
                                    <span><strong>Theo tháng:</strong> Lãi = Dư nợ × (Lãi suất / 12).</span><br>
                                    <span><strong>Theo ngày:</strong> Lãi = Dư nợ × Lãi suất × Số ngày / 365 (tự động dời cuối tuần/ngày lễ).</span><br>
                                    <span><strong>Custom:</strong> Nhập thủ công từng tháng (Gốc + Lãi + Phí = Tổng trả kỳ đó).</span>
                                </p>
                            </div>

                            <div class="col-span-6 sm:col-span-2">
                                <label for="term_months" class="block text-sm font-medium text-content-secondary">Thời hạn (Tháng)</label>
                                <input type="number" name="term_months" id="term_months" x-model.number="months" class="mt-1 focus:ring-primary-500 focus:border-primary-500 block w-full shadow-sm sm:text-sm border-strong bg-surface text-content rounded-md border p-2">
                            </div>

                            <div class="col-span-6 sm:col-span-2" x-show="calculationMethod !== 'custom'">
                                <label for="months_paid" class="block text-sm font-medium text-content-secondary">Đã đóng (Tháng)</label>
                                <input type="number" name="months_paid" id="months_paid" x-model.number="monthsPaid" min="0" :max="months || undefined" class="mt-1 focus:ring-primary-500 focus:border-primary-500 block w-full shadow-sm sm:text-sm border-strong bg-surface text-content rounded-md border p-2" placeholder="0" @input="monthsPaidManual = true">
                                <p class="text-xs text-content-muted mt-1">Tự tính từ ngày bắt đầu → hôm nay. Có thể sửa tay.</p>
                            </div>

                            <div class="col-span-6 sm:col-span-2">
                                <label for="monthly_payment" class="block text-sm font-medium text-content-secondary">
                                    <span x-show="calculationMethod !== 'custom'">Đóng hàng tháng (Dự tính)</span>
                                    <span x-show="calculationMethod === 'custom'">Tổng trả hàng tháng (Custom)</span>
                                </label>
                                <input type="text" inputmode="numeric" name="monthly_payment" id="monthly_payment"
                                    class="money-input mt-1 focus:ring-primary-500 focus:border-primary-500 block w-full shadow-sm sm:text-sm border-strong bg-surface text-content rounded-md border p-2"
                                    x-init="$el.__moneyAlpine = true"
                                    :value="$money.format(monthlyPayment)"
                                    @input="$money.onInput($event.target, v => monthlyPayment = v)"
                                    :readonly="calculationMethod !== 'custom'"
                                    :class="calculationMethod !== 'custom' ? 'bg-app' : ''"
                                    placeholder="Nhập tổng trả mỗi tháng">
                                <p class="text-xs text-content-muted" x-show="calculationMethod === 'custom'">* Mỗi kỳ: Phí = Tổng trả - Gốc - Lãi (không âm).</p>
                            </div>

                            <div class="col-span-6 sm:col-span-2">
                                <label for="payment_day" class="block text-sm font-medium text-content-secondary">Ngày thanh toán cố định (tháng)</label>
                                <input type="number" name="payment_day" id="payment_day" x-model.number="paymentDay" min="1" max="31" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2 text-sm" placeholder="VD: 25">
                                <p class="text-xs text-content-muted mt-1">Thanh toán trước ngày này không trừ gốc. Từ 06/2026 chỉ TT đúng kỳ mới trừ gốc.</p>
                            </div>

                            <!-- Custom schedule input -->
                            <div class="col-span-6" x-show="calculationMethod === 'custom'">
                                <div class="flex items-center justify-between mb-2">
                                    <div>
                                        <label class="block text-sm font-medium text-content-secondary">Lịch trả nợ tùy chỉnh</label>
                                        <p class="text-xs text-content-muted">Tự động sinh số dòng = số tháng. Phí được tính tự động = Tổng trả - Gốc - Lãi.</p>
                                    </div>
                                </div>
                                <div class="overflow-x-auto border rounded-lg">
                                    <table class="min-w-full divide-y divide-default text-xs">
                                        <thead class="bg-app">
                                            <tr>
                                                <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-slate-500">Tháng</th>
                                                <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-slate-500">Ngày trả</th>
                                                <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-slate-500">Gốc</th>
                                                <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-slate-500">Lãi</th>
                                                <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-slate-500">Phí (tự tính)</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-subtle bg-surface">
                                            <template x-for="(row, idx) in customSchedule" :key="idx">
                                                <tr :class="rowValid(row) ? '' : 'bg-red-50 dark:bg-red-900/30'">
                                                    <td class="px-3 py-2">
                                                        <input type="number" :name="`custom_schedule[${idx}][month_index]`" x-model.number="row.month_index" class="w-16 border-strong bg-surface text-content rounded p-1 text-xs">
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <input type="date" :name="`custom_schedule[${idx}][paid_at]`" x-model="row.paid_at" class="border-strong bg-surface text-content rounded p-1 text-xs">
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <input type="text" inputmode="numeric" :name="`custom_schedule[${idx}][principal]`"
                                                            class="money-input w-24 border-strong bg-surface text-content rounded p-1 text-xs"
                                                            x-init="$el.__moneyAlpine = true"
                                                            :value="$money.format(row.principal)"
                                                            @input="$money.onInput($event.target, v => row.principal = v)">
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <input type="text" inputmode="numeric" :name="`custom_schedule[${idx}][interest]`"
                                                            class="money-input w-24 border-strong bg-surface text-content rounded p-1 text-xs"
                                                            x-init="$el.__moneyAlpine = true"
                                                            :value="$money.format(row.interest)"
                                                            @input="$money.onInput($event.target, v => row.interest = v)">
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <input type="text" inputmode="numeric" readonly :name="`custom_schedule[${idx}][fee]`"
                                                            class="money-input w-24 border-strong bg-surface text-content rounded p-1 text-xs bg-muted text-content-secondary"
                                                            x-init="$el.__moneyAlpine = true"
                                                            :value="$money.format(computedFee(row))">
                                                        <input type="hidden" :name="`custom_schedule[${idx}][payment]`" :value="monthlyPayment">
                                                        <p class="text-[10px]" :class="rowValid(row) ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                                            <span x-show="rowValid(row)">OK</span>
                                                            <span x-show="!rowValid(row)">Gốc+Lãi <= Tổng, Phí ≥ 0</span>
                                                        </p>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="col-span-6">
                                <p class="text-sm text-content-muted italic">
                                    Hệ thống sẽ tự động tính toán lịch trả nợ dựa trên thông tin trên.
                                </p>
                            </div>
                        </div>

                        <!-- Preview Schedule (full months) -->
                         <div x-show="type === 'bank' && principal > 0 && rate > 0 && months > 0 && calculationMethod !== 'custom'" class="mt-6 bg-app p-4 rounded-md">
                            <h4 class="font-medium text-content mb-2">Dự tính trả nợ (toàn bộ kỳ)</h4>
                            <div class="flow-root max-h-96 overflow-y-auto">
                                <ul role="list" class="-my-4 divide-y divide-default">
                                    <template x-for="(item, index) in calculatePreview()" :key="index">
                                        <li class="py-3">
                                            <div class="flex items-center space-x-4">
                                                <div class="flex-1 min-w-0">
                                                    <p class="text-sm font-medium text-content truncate">
                                                        Tháng <span x-text="index + 1"></span>
                                                    </p>
                                                    <p class="text-sm text-content-muted truncate">
                                                        Gốc: <span x-text="formatMoney(item.principal)"></span> + Lãi: <span x-text="formatMoney(item.interest)"></span>
                                                    </p>
                                                </div>
                                                <div>
                                                    <span class="inline-flex items-center shadow-sm px-2.5 py-0.5 border border-strong bg-surface text-content text-sm leading-5 font-medium rounded-full text-content-secondary bg-surface hover:bg-surface-hover">
                                                        <span x-text="formatMoney(item.total)"></span>
                                                    </span>
                                                </div>
                                            </div>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </div>

                    </div>
                    <div class="px-4 py-3 bg-app text-right sm:px-6">
                        <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 dark:hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                            Lưu Khoản Nợ
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Confirm modal --}}
    <div x-show="confirmOpen" x-cloak class="fixed z-50 inset-0 overflow-y-auto" role="dialog" aria-modal="true">
        <div class="flex items-end sm:items-center justify-center min-h-screen pt-4 px-4 pb-24 text-center sm:p-0">
            <div x-show="confirmOpen" x-transition.opacity class="fixed inset-0 bg-gray-500 bg-opacity-75" @click="confirmOpen = false"></div>
            <div x-show="confirmOpen" x-transition.scale
                 class="relative inline-block align-bottom bg-surface rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg w-full">
                <div class="bg-surface px-4 pt-5 pb-4 sm:p-6">
                    <h3 class="text-lg font-medium text-content mb-1">Xác nhận tạo khoản vay</h3>
                    <p class="text-sm text-content-muted mb-4">Kiểm tra lại thông số trước khi lưu.</p>
                    <dl class="space-y-2 text-sm border border-subtle rounded-lg divide-y divide-subtle">
                        <div class="flex justify-between gap-4 px-3 py-2">
                            <dt class="text-content-muted">Loại</dt>
                            <dd class="font-medium text-content text-right" x-text="typeLabel()"></dd>
                        </div>
                        <div class="flex justify-between gap-4 px-3 py-2">
                            <dt class="text-content-muted">Tên</dt>
                            <dd class="font-medium text-content text-right" x-text="name || '—'"></dd>
                        </div>
                        <div class="flex justify-between gap-4 px-3 py-2">
                            <dt class="text-content-muted">Số tiền gốc</dt>
                            <dd class="font-medium text-content text-right" x-text="formatMoney(principal)"></dd>
                        </div>
                        <div class="flex justify-between gap-4 px-3 py-2">
                            <dt class="text-content-muted">Ngày bắt đầu</dt>
                            <dd class="font-medium text-content text-right" x-text="startDate || '—'"></dd>
                        </div>
                        <div class="flex justify-between gap-4 px-3 py-2" x-show="recordCashFlow && startsToday() && walletName()">
                            <dt class="text-content-muted">Ví</dt>
                            <dd class="font-medium text-content text-right" x-text="walletName()"></dd>
                        </div>
                        <div class="flex justify-between gap-4 px-3 py-2" x-show="recordCashFlow && startsToday()">
                            <dt class="text-content-muted">Số tiền vào ví</dt>
                            <dd class="font-medium text-content text-right" x-text="formatMoney(receivedAmount)"></dd>
                        </div>
                        <div class="flex justify-between gap-4 px-3 py-2">
                            <dt class="text-content-muted">Ghi nhận vào ví</dt>
                            <dd class="font-medium text-content text-right" x-text="(recordCashFlow && startsToday()) ? 'Có' : 'Không'"></dd>
                        </div>
                        <div class="flex justify-between gap-4 px-3 py-2" x-show="type === 'bank'">
                            <dt class="text-content-muted">Lãi suất</dt>
                            <dd class="font-medium text-content text-right"><span x-text="rate"></span>% / năm</dd>
                        </div>
                        <div class="flex justify-between gap-4 px-3 py-2" x-show="type === 'bank'">
                            <dt class="text-content-muted">Cách tính lãi</dt>
                            <dd class="font-medium text-content text-right" x-text="methodLabel()"></dd>
                        </div>
                        <div class="flex justify-between gap-4 px-3 py-2" x-show="type === 'bank'">
                            <dt class="text-content-muted">Thời hạn</dt>
                            <dd class="font-medium text-content text-right"><span x-text="months"></span> tháng</dd>
                        </div>
                        <div class="flex justify-between gap-4 px-3 py-2" x-show="type === 'bank' && calculationMethod !== 'custom'">
                            <dt class="text-content-muted">Đã đóng</dt>
                            <dd class="font-medium text-content text-right"><span x-text="monthsPaid"></span> tháng</dd>
                        </div>
                        <div class="flex justify-between gap-4 px-3 py-2" x-show="type === 'bank'">
                            <dt class="text-content-muted">Trả hàng tháng</dt>
                            <dd class="font-medium text-content text-right" x-text="formatMoney(monthlyPayment)"></dd>
                        </div>
                        <div class="flex justify-between gap-4 px-3 py-2" x-show="type === 'bank'">
                            <dt class="text-content-muted">Ngày TT cố định</dt>
                            <dd class="font-medium text-content text-right">Ngày <span x-text="paymentDay"></span></dd>
                        </div>
                    </dl>
                </div>
                <div class="bg-app px-4 py-3 sm:px-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                    <button type="button" @click="confirmOpen = false"
                        class="w-full sm:w-auto inline-flex justify-center rounded-md border border-strong bg-surface text-content shadow-sm px-4 py-2 bg-surface text-sm font-medium text-content-secondary hover:bg-surface-hover">
                        Quay lại sửa
                    </button>
                    <button type="button" @click="confirmSubmit()"
                        class="w-full sm:w-auto inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-sm font-medium text-white hover:bg-primary-700 dark:hover:bg-primary-600">
                        Xác nhận tạo
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function loanForm() {
        return {
            type: 'bank',
            name: '',
            principal: 0,
            rate: 10,
            months: 12,
            startDate: '',
            monthsPaid: 0,
            monthsPaidManual: false,
            monthlyPayment: 0,
            calculationMethod: 'monthly',
            paymentDay: {{ (int) old('payment_day', 25) }},
            walletId: '{{ old('wallet_id', '') }}',
            recordCashFlow: true,
            receivedAmount: 0,
            receivedTouched: false,
            confirmOpen: false,
            submitting: false,
            customSchedule: [],
            wallets: @js($wallets->map(fn ($w) => ['id' => (string) $w->id, 'name' => $w->name])->values()),

            init() {
                this.$watch('principal', (val) => {
                    this.calculateMonthly();
                    if (!this.receivedTouched) {
                        this.receivedAmount = val;
                    }
                });
                this.$watch('rate', () => this.calculateMonthly());
                this.$watch('months', (newVal, oldVal) => {
                    this.calculateMonthly();
                    this.syncMonthsPaid();
                    if (this.calculationMethod === 'custom') {
                        this.syncCustomRows(newVal, oldVal);
                    }
                });
                this.$watch('startDate', () => {
                    this.syncMonthsPaid(true);
                    if (this.calculationMethod === 'custom') {
                        this.updateCustomDates();
                    }
                });
                this.$watch('calculationMethod', (newVal, oldVal) => {
                    if (newVal === 'custom') {
                        this.syncCustomRows(this.months, this.customSchedule.length);
                        this.updateCustomDates();
                    }
                });
            },

            onSubmit(e) {
                if (this.submitting) return;
                e.preventDefault();
                if (!this.$refs.loanForm.checkValidity()) {
                    this.$refs.loanForm.reportValidity();
                    return;
                }
                this.confirmOpen = true;
            },

            confirmSubmit() {
                this.submitting = true;
                this.confirmOpen = false;
                this.$nextTick(() => this.$refs.loanForm.requestSubmit());
            },

            typeLabel() {
                return { bank: 'Vay ngân hàng', borrow: 'Mượn nợ', lend: 'Cho mượn' }[this.type] || this.type;
            },

            methodLabel() {
                return { monthly: 'Theo tháng', daily: 'Theo ngày (Actual/365)', custom: 'Tùy chỉnh' }[this.calculationMethod] || this.calculationMethod;
            },

            startsToday() {
                if (!this.startDate) return false;
                const parts = this.startDate.split('/');
                if (parts.length !== 3) return false;
                const d = new Date(Number(parts[2]), Number(parts[1]) - 1, Number(parts[0]));
                if (isNaN(d.getTime())) return false;
                const now = new Date();
                return d.getFullYear() === now.getFullYear()
                    && d.getMonth() === now.getMonth()
                    && d.getDate() === now.getDate();
            },

            walletName() {
                if (!this.walletId) return '';
                const w = this.wallets.find(x => String(x.id) === String(this.walletId));
                return w ? w.name : '';
            },

            /** Số tháng đã qua từ ngày bắt đầu đến hôm nay (làm tròn xuống, tối đa = thời hạn). */
            syncMonthsPaid(force = false) {
                if (this.monthsPaidManual && !force) {
                    if (this.months > 0 && this.monthsPaid > this.months) {
                        this.monthsPaid = this.months;
                    }
                    return;
                }
                if (force) {
                    this.monthsPaidManual = false;
                }
                if (!this.startDate) {
                    this.monthsPaid = 0;
                    return;
                }
                const parts = this.startDate.split('/');
                if (parts.length !== 3) return;
                const start = new Date(Number(parts[2]), Number(parts[1]) - 1, Number(parts[0]));
                if (isNaN(start.getTime())) return;

                const now = new Date();
                let diff = (now.getFullYear() - start.getFullYear()) * 12 + (now.getMonth() - start.getMonth());
                if (now.getDate() < start.getDate()) {
                    diff -= 1;
                }
                diff = Math.max(0, diff);
                if (this.months > 0) {
                    diff = Math.min(diff, this.months);
                }
                this.monthsPaid = diff;
            },

            calculateMonthly() {
                if (this.type === 'bank' && this.principal > 0 && this.rate > 0 && this.months > 0 && this.calculationMethod !== 'custom') {
                    const r = (this.rate / 100) / 12;
                    const n = this.months;
                    const p = this.principal;

                    // Annuity formula
                    const m = (p * r * Math.pow(1 + r, n)) / (Math.pow(1 + r, n) - 1);
                    this.monthlyPayment = Math.round(m);
                }
            },

            addCustomRow() {
                const idx = this.customSchedule.length;
                this.customSchedule.push({
                    month_index: idx + 1,
                    paid_at: '',
                    principal: 0,
                    interest: 0,
                });
            },

            removeCustomRow(index) {
                this.customSchedule.splice(index, 1);
            },

            syncCustomRows(newMonths, oldMonths) {
                if (!newMonths || newMonths < 1) return;
                // Confirm when reducing number of rows
                if (oldMonths && newMonths < oldMonths) {
                    if (!confirm('Giảm số tháng sẽ xóa các dòng cuối. Tiếp tục?')) {
                        this.months = oldMonths;
                        return;
                    }
                }
                // Adjust rows length
                while (this.customSchedule.length < newMonths) {
                    this.addCustomRow();
                }
                while (this.customSchedule.length > newMonths) {
                    this.customSchedule.pop();
                }
                this.updateCustomDates();
            },

            updateCustomDates() {
                if (!this.startDate) return;
                const base = new Date(this.startDate.split('/').reverse().join('-'));
                if (isNaN(base)) return;
                this.customSchedule.forEach((row, idx) => {
                    const d = new Date(base);
                    d.setMonth(d.getMonth() + idx + 1);
                    const iso = d.toISOString().split('T')[0];
                    row.paid_at = iso;
                    row.month_index = idx + 1;
                });
            },

            rowValid(row) {
                const fee = this.computedFee(row);
                const sum = Number(row.principal || 0) + Number(row.interest || 0) + Number(fee || 0);
                return fee >= 0 && Math.round(sum) === Math.round(this.monthlyPayment || 0);
            },

            computedFee(row) {
                const fee = (this.monthlyPayment || 0) - (Number(row.principal || 0) + Number(row.interest || 0));
                return fee;
            },

            calculatePreview() {
                let items = [];
                if (this.principal > 0 && this.rate > 0 && this.months > 0) {
                    let balance = this.principal;
                    const r = (this.rate / 100) / 12;
                    const m = this.monthlyPayment;

                    for(let i=0; i<this.months; i++) {
                        if(balance <= 0) break;
                        let interest = balance * r;
                        let principalPaid = m - interest;
                        balance -= principalPaid;
                        items.push({
                            interest: interest,
                            principal: principalPaid,
                            total: m
                        });
                    }
                }
                return items;
            },

            formatMoney(value) {
                return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
            }
        }
    }
</script>
@endsection

