<?php

namespace App\Http\Controllers;

use App\Models\TransactionTemplate;
use App\Support\InertiaData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Wallets\Catalog\Application\Command\CreateTransactionTemplate;
use Wallets\Catalog\Application\Command\DeleteTransactionTemplate;
use Wallets\Catalog\Application\Query\ListTemplates;
use Wallets\Shared\Application\CommandBus;
use Wallets\Shared\Application\QueryBus;
use Wallets\WalletAccounting\Application\Query\ListWallets;

class TransactionTemplateController extends Controller
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {}

    public function index()
    {
        $templates = $this->queries->ask(new ListTemplates(userId: auth()->id()));
        $wallets = $this->queries->ask(new ListWallets(userId: auth()->id(), activeOnly: true));

        return Inertia::render('TransactionTemplates/Index', [
            'templates' => $templates->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'type' => $t->type,
                'type_label' => $t->typeLabel(),
                'amount' => (float) $t->amount,
                'fee' => (float) ($t->fee ?? 0),
                'description' => $t->description,
                'category' => $t->category,
                'adjustment_direction' => $t->adjustment_direction,
                'default_wallet_name' => $t->defaultWallet->name ?? null,
                'from_wallet_name' => $t->fromWallet->name ?? null,
                'to_wallet_name' => $t->toWallet->name ?? null,
            ])->values()->all(),
            'wallets' => InertiaData::wallets($wallets),
        ]);
    }

    public function store(Request $request)
    {
        $type = $request->input('type');

        $rules = [
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense,adjustment,transfer',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ];

        if ($type === 'transfer') {
            $rules['from_wallet_id'] = 'required|exists:wallets,id';
            $rules['to_wallet_id'] = 'required|exists:wallets,id|different:from_wallet_id';
            $rules['fee'] = 'nullable|numeric|min:0';
        } elseif ($type === 'adjustment') {
            $rules['default_wallet_id'] = 'required|exists:wallets,id';
            $rules['adjustment_direction'] = 'required|in:increase,decrease';
        } else {
            $rules['category'] = 'nullable|string|max:255';
            $rules['default_wallet_id'] = 'nullable|exists:wallets,id';
        }

        $validated = $request->validate($rules);
        $validated['fee'] = $type === 'transfer' ? (float) ($validated['fee'] ?? 0) : null;

        $this->commands->dispatch(new CreateTransactionTemplate(
            userId: auth()->id(),
            data: $validated,
        ));

        return redirect()->route('transaction-templates.index')->with('success', 'Đã tạo mẫu giao dịch.');
    }

    public function destroy(TransactionTemplate $transactionTemplate)
    {
        $this->commands->dispatch(new DeleteTransactionTemplate(
            userId: auth()->id(),
            templateId: $transactionTemplate->id,
        ));

        return redirect()->route('transaction-templates.index')->with('success', 'Đã xóa mẫu.');
    }
}
