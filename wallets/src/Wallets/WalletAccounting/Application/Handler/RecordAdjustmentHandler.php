<?php

namespace Wallets\WalletAccounting\Application\Handler;

use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\Wallet;
use DomainException;
use Illuminate\Support\Facades\DB;
use Wallets\Shared\Application\Command;
use Wallets\Shared\Application\CommandHandler;
use Wallets\WalletAccounting\Application\Command\RecordAdjustment;

final class RecordAdjustmentHandler implements CommandHandler
{
    public function handle(Command $command): mixed
    {
        assert($command instanceof RecordAdjustment);

        $data = $command->data;

        return DB::transaction(function () use ($command, $data) {
            $wallet = Wallet::query()->forUser($command->userId)->lockForUpdate()->findOrFail($data['wallet_id']);
            $currentBalance = (float) $wallet->balance;
            $targetBalance = round((float) $data['target_balance'], 2);
            $delta = round($targetBalance - $currentBalance, 2);

            if (abs($delta) < 0.01) {
                throw new DomainException('Số dư cuối trùng số dư hiện tại, không cần cân đối.');
            }

            $direction = $delta > 0 ? 'increase' : 'decrease';
            $amount = abs($delta);
            $cashType = $direction === 'increase' ? 'income' : 'expense';

            $tx = Transaction::create([
                'user_id' => $command->userId,
                'wallet_id' => $data['wallet_id'],
                'type' => 'adjustment',
                'adjustment_direction' => $direction,
                'amount' => $amount,
                'description' => $data['description'],
                'category' => 'Cân đối',
                'transaction_template_id' => $data['transaction_template_id'] ?? null,
                'transacted_at' => $data['transacted_at'],
                'note' => $data['note'] ?? null,
            ]);

            $wallet->applyTransaction($cashType, $amount);

            if ($command->saveAsTemplate && empty($data['transaction_template_id'])) {
                TransactionTemplate::create([
                    'user_id' => $command->userId,
                    'name' => $command->templateName ?? $data['description'],
                    'type' => 'adjustment',
                    'amount' => $amount,
                    'adjustment_direction' => $direction,
                    'description' => $data['description'],
                    'default_wallet_id' => $data['wallet_id'],
                ]);
            }

            return $tx;
        });
    }
}
