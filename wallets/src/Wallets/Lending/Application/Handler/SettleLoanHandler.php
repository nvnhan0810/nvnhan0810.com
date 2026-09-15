<?php

namespace Wallets\Lending\Application\Handler;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Wallets\Lending\Application\Command\SettleLoan;
use Wallets\Lending\Application\LoanWalletService;
use Wallets\Shared\Application\Command;
use Wallets\Shared\Application\CommandHandler;

final class SettleLoanHandler implements CommandHandler
{
    public function __construct(private readonly LoanWalletService $loanWallet) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof SettleLoan);

        $loan = Loan::query()->forUser($command->userId)->with('payments')->findOrFail($command->loanId);
        $data = $command->data;

        DB::transaction(function () use ($loan, $data, $command) {
            $amount = $data['amount'] ?? $this->loanWallet->remainingPayoff(
                $loan,
                $command->remainingPrincipal
            );

            if ($amount > 0) {
                $paidAt = $data['paid_at'] ?? now()->format('Y-m-d');
                $wallet = Wallet::query()->forUser($command->userId)->findOrFail($data['wallet_id']);

                $payment = Payment::create([
                    'loan_id' => $loan->id,
                    'kind' => Payment::KIND_SETTLEMENT,
                    'amount' => $amount,
                    'paid_at' => $paidAt,
                    'note' => $data['note'] ?? 'Tất toán',
                    'reduces_principal' => true,
                ]);

                $this->loanWallet->recordPayment($loan, $payment, $wallet);

                if (! $loan->wallet_id) {
                    $loan->update(['wallet_id' => $wallet->id]);
                }
            }

            $loan->update(['is_settled' => true]);
        });

        return $loan->fresh();
    }
}
