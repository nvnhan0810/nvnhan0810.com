<?php

namespace Wallets\WalletAccounting\Application\Handler;

use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WalletTransfer;
use DomainException;
use Illuminate\Support\Facades\DB;
use Wallets\Shared\Application\Command;
use Wallets\Shared\Application\CommandHandler;
use Wallets\WalletAccounting\Application\Command\DeleteTransaction;
use Wallets\WalletAccounting\Application\WalletTransferService;

final class DeleteTransactionHandler implements CommandHandler
{
    public function __construct(private readonly WalletTransferService $transfers) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof DeleteTransaction);

        $transaction = Transaction::query()->forUser($command->userId)->findOrFail($command->transactionId);

        if ($transaction->isFromLoan()) {
            throw new DomainException('Giao dịch liên kết khoản vay. Xóa tại trang Khoản vay nếu cần.');
        }

        if ($transaction->wallet_transfer_id) {
            $transfer = WalletTransfer::query()->forUser($command->userId)->findOrFail($transaction->wallet_transfer_id);
            $this->transfers->reverse($transfer);

            return ['type' => 'transfer'];
        }

        DB::transaction(function () use ($command, $transaction) {
            $wallet = Wallet::query()->forUser($command->userId)->lockForUpdate()->findOrFail($transaction->wallet_id);
            $effectiveType = $transaction->isAdjustment()
                ? ($transaction->adjustment_direction === 'increase' ? 'income' : 'expense')
                : $transaction->type;
            $wallet->reverseTransaction($effectiveType, (float) $transaction->amount);
            $transaction->delete();
        });

        return ['type' => 'transaction'];
    }
}
