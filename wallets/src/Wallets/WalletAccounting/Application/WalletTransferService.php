<?php

namespace Wallets\WalletAccounting\Application;

use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletTransfer;
use Illuminate\Support\Facades\DB;

class WalletTransferService
{
    public function record(
        int $userId,
        Wallet $fromWallet,
        Wallet $toWallet,
        float $amount,
        float $fee,
        string $description,
        string $transactedAt,
        ?string $note = null,
        ?int $templateId = null,
    ): WalletTransfer {
        if ($fromWallet->id === $toWallet->id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'to_wallet_id' => 'Ví nguồn và ví đích phải khác nhau.',
            ]);
        }

        $amount = abs($amount);
        $fee = max(0, $fee);
        $totalOut = $amount + $fee;

        return DB::transaction(function () use ($userId, $fromWallet, $toWallet, $amount, $fee, $totalOut, $description, $transactedAt, $note, $templateId) {
            $from = Wallet::query()->forUser($userId)->lockForUpdate()->findOrFail($fromWallet->id);
            $to = Wallet::query()->forUser($userId)->lockForUpdate()->findOrFail($toWallet->id);

            $transfer = WalletTransfer::create([
                'user_id' => $userId,
                'from_wallet_id' => $from->id,
                'to_wallet_id' => $to->id,
                'amount' => $amount,
                'fee' => $fee,
                'description' => $description,
                'note' => $note,
                'transacted_at' => $transactedAt,
                'transaction_template_id' => $templateId,
            ]);

            $outDesc = $description ?: "Chuyển đến {$to->name}";
            if ($fee > 0) {
                $outDesc .= ' (phí '.number_format($fee, 0).' ₫)';
            }

            Transaction::create([
                'user_id' => $userId,
                'wallet_id' => $from->id,
                'type' => 'expense',
                'amount' => $totalOut,
                'description' => $outDesc,
                'category' => 'Chuyển ví',
                'transacted_at' => $transactedAt,
                'note' => $note,
                'transaction_template_id' => $templateId,
                'wallet_transfer_id' => $transfer->id,
            ]);
            $from->applyTransaction('expense', $totalOut);

            $inDesc = $description ?: "Nhận từ {$from->name}";

            Transaction::create([
                'user_id' => $userId,
                'wallet_id' => $to->id,
                'type' => 'income',
                'amount' => $amount,
                'description' => $inDesc,
                'category' => 'Chuyển ví',
                'transacted_at' => $transactedAt,
                'note' => $note,
                'transaction_template_id' => $templateId,
                'wallet_transfer_id' => $transfer->id,
            ]);
            $to->applyTransaction('income', $amount);

            return $transfer;
        });
    }

    public function reverse(WalletTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            $transfer->load('transactions.wallet');

            foreach ($transfer->transactions as $transaction) {
                $wallet = Wallet::query()->forUser($transfer->user_id)->lockForUpdate()->findOrFail($transaction->wallet_id);
                $wallet->reverseTransaction($transaction->type, (float) $transaction->amount);
                $transaction->delete();
            }

            $transfer->delete();
        });
    }
}
