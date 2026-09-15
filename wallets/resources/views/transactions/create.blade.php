@extends('layouts.app')

@section('content')
@php
    $templateData = $templates->map(fn ($t) => [
        'id' => $t->id,
        'name' => $t->name,
        'type' => $t->type,
        'amount' => (float) $t->amount,
        'fee' => (float) ($t->fee ?? 0),
        'category' => $t->category,
        'description' => $t->description,
        'default_wallet_id' => $t->default_wallet_id,
        'from_wallet_id' => $t->from_wallet_id,
        'to_wallet_id' => $t->to_wallet_id,
        'adjustment_direction' => $t->adjustment_direction,
    ])->values();
    $walletBalances = $wallets->mapWithKeys(fn ($w) => [(string) $w->id => (float) $w->balance]);
    $defaultWalletId = old('wallet_id', $prefill['wallet_id'] ?? $selectedTemplate?->default_wallet_id ?? $wallets->first()?->id ?? '');
    $initial = [
        'template_id' => old('transaction_template_id', $selectedTemplate?->id ?? ''),
        'wallet_id' => $defaultWalletId,
        'from_wallet_id' => old('from_wallet_id', $selectedTemplate?->from_wallet_id ?? ''),
        'to_wallet_id' => old('to_wallet_id', $selectedTemplate?->to_wallet_id ?? ''),
        'type' => old('type', $prefill['type'] ?? $selectedTemplate?->type ?? 'expense'),
        'amount' => old('amount', $prefill['amount'] ?? $selectedTemplate?->amount ?? ''),
        'target_balance' => old('target_balance', ''),
        'fee' => old('fee', $selectedTemplate?->fee ?? 0),
        'description' => old('description', $prefill['description'] ?? $selectedTemplate?->description ?? ''),
        'category' => old('category', $prefill['category'] ?? $selectedTemplate?->category ?? ''),
        'from_template' => (bool) ($selectedTemplate || old('transaction_template_id')),
        'save_as_template' => (bool) old('save_as_template'),
        'template_name' => old('template_name', ''),
    ];
@endphp

<div class="max-w-2xl mx-auto" x-data="transactionForm(@js($templateData), @js($walletBalances), @js($initial))">
    <h2 class="text-2xl font-bold text-content mb-6">Ghi giao dịch</h2>
    @include('partials.flash')

    <form action="{{ route('transactions.store') }}" method="POST" class="bg-surface shadow rounded-lg p-6 space-y-4">
        @csrf
        <input type="hidden" name="transaction_template_id" :value="from_template ? template_id : ''">
        <input type="hidden" name="recurring_item_id" value="{{ old('recurring_item_id', $prefill['recurring_item_id'] ?? '') }}">
        <input type="hidden" name="recurring_occurrence_id" value="{{ old('recurring_occurrence_id', $prefill['recurring_occurrence_id'] ?? '') }}">

        <div>
            <label class="block text-sm font-medium text-content-secondary">Chọn mẫu (tùy chọn)</label>
            <select @change="applyTemplate($event.target.value)" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2">
                <option value="">— Không dùng mẫu —</option>
                @foreach($templates as $t)
                    <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->typeLabel() }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-content-secondary">Loại giao dịch</label>
            <select name="type" x-model="type" required class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2">
                <option value="expense">Chi</option>
                <option value="income">Thu</option>
                <option value="adjustment">Cân đối</option>
                <option value="transfer">Chuyển / Rút ví</option>
            </select>
        </div>

        {{-- Thu / Chi --}}
        <template x-if="type === 'income' || type === 'expense'">
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Ví</label>
                        <select name="wallet_id" x-model="wallet_id" :required="type === 'income' || type === 'expense'" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2">
                            <option value="">Chọn ví</option>
                            @foreach($wallets as $w)
                                <option value="{{ $w->id }}">{{ $w->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Số tiền (₫)</label>
                        <x-money-input name="amount" alpine-model="amount" required class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2" />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-content-secondary">Mô tả</label>
                    <input type="text" name="description" x-model="description" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-content-secondary">Danh mục</label>
                    <input type="text" name="category" x-model="category" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2">
                </div>
            </div>
        </template>

        {{-- Cân đối --}}
        <template x-if="type === 'adjustment'">
            <div class="space-y-4 rounded-lg border border-amber-200 bg-amber-50/30 p-4">
                <p class="text-xs text-amber-800">Nhập số dư cuối cùng cần khớp. Hệ thống tự tính mức tăng/giảm so với số dư hiện tại.</p>
                <div>
                    <label class="block text-sm font-medium text-content-secondary">Ví</label>
                    <select name="wallet_id" x-model="wallet_id" required class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2">
                        @foreach($wallets as $w)
                            <option value="{{ $w->id }}">{{ $w->name }} ({{ number_format($w->balance, 0) }} ₫)</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Số dư hiện tại</label>
                        <p class="mt-1 block w-full rounded-md border border-subtle bg-app text-content p-2 text-sm font-semibold" x-text="formatMoney(currentBalance())"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Số dư cuối cùng (₫)</label>
                        <x-money-input name="target_balance" alpine-model="target_balance" required class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2" />
                        @error('target_balance')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <p class="text-xs text-amber-900" x-show="adjustmentPreview()" x-cloak>
                    Điều chỉnh: <span class="font-semibold" x-text="adjustmentPreview()"></span>
                </p>
                <div>
                    <label class="block text-sm font-medium text-content-secondary">Lý do</label>
                    <input type="text" name="description" x-model="description" required placeholder="VD: Đối chiếu sao kê tháng 5" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2">
                </div>
            </div>
        </template>

        {{-- Chuyển ví --}}
        <template x-if="type === 'transfer'">
            <div class="space-y-4 rounded-lg border border-blue-200 bg-blue-50/30 p-4">
                <p class="text-xs text-blue-800">Rút/chuyển từ ví nguồn sang ví đích. Phí trừ thêm ở ví nguồn.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Từ ví</label>
                        <select name="from_wallet_id" x-model="from_wallet_id" required class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2">
                            <option value="">Chọn ví nguồn</option>
                            @foreach($wallets as $w)
                                <option value="{{ $w->id }}">{{ $w->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Đến ví</label>
                        <select name="to_wallet_id" x-model="to_wallet_id" required class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2">
                            <option value="">Chọn ví đích</option>
                            @foreach($wallets as $w)
                                <option value="{{ $w->id }}">{{ $w->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Số tiền chuyển (₫)</label>
                        <x-money-input name="amount" alpine-model="amount" required class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-content-secondary">Phí (₫)</label>
                        <x-money-input name="fee" alpine-model="fee" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2" />
                    </div>
                </div>
                <p class="text-xs text-gray-600 dark:text-slate-500">Ví nguồn trừ: <span class="font-semibold" x-text="formatMoney((Number(amount)||0) + (Number(fee)||0))"></span></p>
                <div>
                    <label class="block text-sm font-medium text-content-secondary">Mô tả</label>
                    <input type="text" name="description" x-model="description" required placeholder="VD: Chuyển tiền mặt sang ngân hàng" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2">
                </div>
            </div>
        </template>

        <div>
            <label class="block text-sm font-medium text-content-secondary">Ngày giao dịch</label>
            <input type="text" name="transacted_at" value="{{ old('transacted_at', $prefill['transacted_at'] ?? date('d/m/Y')) }}" required class="datepicker mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-content-secondary">Ghi chú</label>
            <textarea name="note" rows="2" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2">{{ old('note') }}</textarea>
        </div>

        <div x-show="!from_template" x-cloak class="rounded-md bg-app p-4 border border-default">
            <label class="flex items-center gap-2 text-sm text-content-secondary">
                <input type="checkbox" name="save_as_template" value="1" x-model="save_as_template" class="rounded border-strong bg-surface text-content text-primary-600 dark:text-primary-400">
                Lưu thành mẫu
            </label>
            <div x-show="save_as_template" class="mt-3">
                <input type="text" name="template_name" x-model="template_name" class="mt-1 block w-full rounded-md border border-strong bg-surface text-content p-2 text-sm" placeholder="Tên mẫu">
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="px-4 py-2 bg-primary-600 dark:bg-primary-500 text-white rounded-md text-sm font-medium hover:bg-primary-700 dark:hover:bg-primary-600">Lưu</button>
            <a href="{{ route('transactions.index') }}" class="px-4 py-2 border border-strong bg-surface text-content rounded-md text-sm text-content-secondary hover:bg-surface-hover">Hủy</a>
        </div>
    </form>
</div>

<script>
function transactionForm(templates, walletBalances, initial) {
    return {
        templates,
        walletBalances,
        template_id: initial.template_id || '',
        wallet_id: String(initial.wallet_id || ''),
        from_wallet_id: String(initial.from_wallet_id || ''),
        to_wallet_id: String(initial.to_wallet_id || ''),
        type: initial.type || 'expense',
        amount: initial.amount || '',
        target_balance: initial.target_balance === '' || initial.target_balance === null ? '' : Number(initial.target_balance),
        fee: initial.fee || 0,
        description: initial.description || '',
        category: initial.category || '',
        from_template: initial.from_template || false,
        save_as_template: initial.save_as_template || false,
        template_name: initial.template_name || '',
        formatMoney(n) {
            return new Intl.NumberFormat('vi-VN').format(Math.round(n || 0)) + ' ₫';
        },
        currentBalance() {
            const bal = this.walletBalances[String(this.wallet_id)];
            return bal == null ? 0 : Number(bal);
        },
        adjustmentDelta() {
            if (this.target_balance === '' || this.target_balance === null) return null;
            return Math.round(Number(this.target_balance) - this.currentBalance());
        },
        adjustmentPreview() {
            const delta = this.adjustmentDelta();
            if (delta === null || !Number.isFinite(delta) || delta === 0) return '';
            const sign = delta > 0 ? '+' : '−';
            return sign + this.formatMoney(Math.abs(delta)).replace(' ₫', '') + ' ₫';
        },
        applyTemplate(id) {
            if (!id) {
                this.from_template = false;
                this.template_id = '';
                return;
            }
            const t = this.templates.find(x => String(x.id) === String(id));
            if (!t) return;
            this.from_template = true;
            this.template_id = t.id;
            this.type = t.type;
            this.amount = t.amount;
            this.fee = t.fee || 0;
            this.description = t.description || t.name;
            this.category = t.category || '';
            if (t.default_wallet_id) this.wallet_id = String(t.default_wallet_id);
            if (t.from_wallet_id) this.from_wallet_id = String(t.from_wallet_id);
            if (t.to_wallet_id) this.to_wallet_id = String(t.to_wallet_id);
            if (t.type === 'adjustment') {
                const signed = (t.adjustment_direction === 'decrease' ? -1 : 1) * Number(t.amount || 0);
                this.target_balance = this.currentBalance() + signed;
            }
        },
        init() {
            if (this.template_id) this.applyTemplate(this.template_id);
        }
    };
}
</script>
@endsection
