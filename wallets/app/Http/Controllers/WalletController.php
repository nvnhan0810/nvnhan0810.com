<?php

namespace App\Http\Controllers;

use DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use App\Support\InertiaData;
use Wallets\Shared\Application\CommandBus;
use Wallets\Shared\Application\QueryBus;
use Wallets\WalletAccounting\Application\Command\CreateWallet;
use Wallets\WalletAccounting\Application\Command\DeleteWallet;
use Wallets\WalletAccounting\Application\Command\UpdateWallet;
use Wallets\WalletAccounting\Application\Query\ListWallets;
use App\Models\Wallet;

class WalletController extends Controller
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {}

    public function index()
    {
        $wallets = $this->queries->ask(new ListWallets(
            userId: auth()->id(),
            withTransactionCount: true,
        ));

        return Inertia::render('Wallets/Index', [
            'wallets' => InertiaData::wallets($wallets, withCount: true),
            'walletTypes' => Wallet::TYPES,
        ]);
    }

    public function create()
    {
        return Inertia::render('Wallets/Form', [
            'wallet' => null,
            'walletTypes' => Wallet::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $this->commands->dispatch(new CreateWallet(
            userId: auth()->id(),
            data: $this->validatedWalletData($request),
        ));

        return redirect()->route('wallets.index')->with('success', 'Đã tạo ví.');
    }

    public function edit(Wallet $wallet)
    {
        return Inertia::render('Wallets/Form', [
            'wallet' => InertiaData::wallet($wallet),
            'walletTypes' => Wallet::TYPES,
        ]);
    }

    public function update(Request $request, Wallet $wallet)
    {
        $this->commands->dispatch(new UpdateWallet(
            userId: auth()->id(),
            walletId: $wallet->id,
            data: $this->validatedWalletData($request, $wallet),
        ));

        return redirect()->route('wallets.index')->with('success', 'Đã cập nhật ví.');
    }

    public function destroy(Wallet $wallet)
    {
        try {
            $this->commands->dispatch(new DeleteWallet(
                userId: auth()->id(),
                walletId: $wallet->id,
            ));
        } catch (DomainException $e) {
            return back()->withErrors(['wallet' => $e->getMessage()]);
        }

        return redirect()->route('wallets.index')->with('success', 'Đã xóa ví.');
    }

    public function sort()
    {
        return redirect()->route('wallets.index');
    }

    public function updateSort(Request $request)
    {
        $request->validate([
            'wallets' => 'required|array',
            'wallets.*.id' => 'required|integer|exists:wallets,id',
            'wallets.*.is_pinned' => 'required|boolean',
            'wallets.*.order' => 'required|integer',
        ]);

        foreach ($request->input('wallets') as $walletData) {
            Wallet::where('id', $walletData['id'])
                ->where('user_id', auth()->id())
                ->update([
                    'is_pinned' => $walletData['is_pinned'],
                    'order' => $walletData['order'],
                ]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('wallets.index')->with('success', 'Đã lưu thứ tự ví.');
    }

    private function validatedWalletData(Request $request, ?Wallet $wallet = null): array
    {
        $isCreditCard = $request->input('type') === 'credit_card';
        $isEdit = $wallet !== null;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => ['required', Rule::in(array_keys(Wallet::TYPES))],
            'balance' => [Rule::requiredIf(! $isCreditCard && ! $isEdit), 'nullable', 'numeric'],
            'credit_limit' => [Rule::requiredIf($isCreditCard), 'nullable', 'numeric', 'min:0'],
            'statement_day' => [Rule::requiredIf($isCreditCard), 'nullable', 'integer', 'min:1', 'max:31'],
            'payment_day' => [Rule::requiredIf($isCreditCard), 'nullable', 'integer', 'min:1', 'max:31'],
            'outstanding_balance' => [Rule::requiredIf($isCreditCard && ! $isEdit), 'nullable', 'numeric', 'min:0'],
            'notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $data = [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'notes' => $validated['notes'] ?? null,
        ];

        if ($wallet) {
            $data['is_active'] = $request->boolean('is_active');
        } else {
            $data['is_active'] = true;
        }

        if ($isCreditCard) {
            $limit = (float) $validated['credit_limit'];
            $data['credit_limit'] = $limit;
            $data['statement_day'] = (int) $validated['statement_day'];
            $data['payment_day'] = (int) $validated['payment_day'];

            if (! $isEdit) {
                $outstanding = (float) $validated['outstanding_balance'];
                if ($outstanding > $limit) {
                    throw ValidationException::withMessages([
                        'outstanding_balance' => 'Dư nợ không được vượt hạn mức.',
                    ]);
                }
                $data['outstanding_balance'] = $outstanding;
                $data['balance'] = max(0, $limit - $outstanding);
            }
        } elseif (! $isEdit) {
            $data['balance'] = (float) ($validated['balance'] ?? 0);
            $data['credit_limit'] = null;
            $data['statement_day'] = null;
            $data['payment_day'] = null;
            $data['outstanding_balance'] = null;
        } elseif ($isEdit && $wallet->isCreditCard() && ! $isCreditCard) {
            $data['credit_limit'] = null;
            $data['statement_day'] = null;
            $data['payment_day'] = null;
            $data['outstanding_balance'] = null;
        }

        return $data;
    }
}
