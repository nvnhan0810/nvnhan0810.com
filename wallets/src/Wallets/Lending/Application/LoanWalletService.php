<?php

namespace Wallets\Lending\Application;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class LoanWalletService
{
    public function cashFlowTypeForCreation(string $loanType): string
    {
        return match ($loanType) {
            'lend' => 'expense',
            default => 'income',
        };
    }

    public function cashFlowTypeForPayment(string $loanType): string
    {
        return match ($loanType) {
            'lend' => 'income',
            default => 'expense',
        };
    }

    public function creationDescription(Loan $loan): string
    {
        return match ($loan->type) {
            'lend' => 'Cho mượn: '.$loan->name,
            'borrow' => 'Nhận mượn: '.$loan->name,
            'bank' => 'Giải ngân vay: '.$loan->name,
            default => $loan->name,
        };
    }

    public function paymentDescription(Loan $loan, ?string $note = null): string
    {
        $prefix = match ($loan->type) {
            'lend' => 'Thu hồi cho mượn',
            'borrow' => 'Trả nợ mượn',
            'bank' => 'Trả khoản vay',
            default => 'Thanh toán khoản vay',
        };

        $text = $prefix.': '.$loan->name;

        return $note ? $text.' — '.$note : $text;
    }

    public function remainingPayoff(Loan $loan, ?float $remainingPrincipal = null): float
    {
        $paid = (float) $loan->payments()->sum('amount');

        if ($loan->type === 'bank' && $remainingPrincipal !== null) {
            return max(0, (float) $remainingPrincipal);
        }

        return max(0, (float) $loan->principal_amount - $paid);
    }

    public function recordCreation(Loan $loan, Wallet $wallet, ?float $amount = null): Transaction
    {
        $type = $this->cashFlowTypeForCreation($loan->type);
        $amount = $amount !== null ? max(0, $amount) : (float) $loan->principal_amount;

        return $this->createLinkedTransaction(
            wallet: $wallet,
            type: $type,
            amount: $amount,
            description: $this->creationDescription($loan),
            transactedAt: $loan->started_at->format('Y-m-d'),
            loanId: $loan->id,
            category: 'Khoản vay',
        );
    }

    public function recordPayment(Loan $loan, Payment $payment, Wallet $wallet): Transaction
    {
        $type = $this->cashFlowTypeForPayment($loan->type);
        $amount = (float) $payment->amount;

        $transaction = $this->createLinkedTransaction(
            wallet: $wallet,
            type: $type,
            amount: $amount,
            description: $this->paymentDescription($loan, $payment->note),
            transactedAt: $payment->paid_at->format('Y-m-d'),
            loanId: $loan->id,
            loanPaymentId: $payment->id,
            category: 'Khoản vay',
        );

        $payment->update(['transaction_id' => $transaction->id]);

        return $transaction;
    }

    private function createLinkedTransaction(
        Wallet $wallet,
        string $type,
        float $amount,
        string $description,
        string $transactedAt,
        ?int $loanId = null,
        ?int $loanPaymentId = null,
        ?string $category = null,
    ): Transaction {
        return DB::transaction(function () use ($wallet, $type, $amount, $description, $transactedAt, $loanId, $loanPaymentId, $category) {
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);

            $transaction = Transaction::create([
                'user_id' => $wallet->user_id,
                'wallet_id' => $wallet->id,
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
                'category' => $category,
                'transacted_at' => $transactedAt,
                'loan_id' => $loanId,
                'loan_payment_id' => $loanPaymentId,
            ]);

            $wallet->applyTransaction($type, $amount);

            return $transaction;
        });
    }
}
