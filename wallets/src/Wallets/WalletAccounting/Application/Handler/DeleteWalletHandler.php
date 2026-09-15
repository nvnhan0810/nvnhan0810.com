<?php

namespace Wallets\WalletAccounting\Application\Handler;

use App\Models\Wallet;
use DomainException;
use Wallets\Shared\Application\Command;
use Wallets\Shared\Application\CommandHandler;
use Wallets\WalletAccounting\Application\Command\DeleteWallet;

final class DeleteWalletHandler implements CommandHandler
{
    public function handle(Command $command): mixed
    {
        assert($command instanceof DeleteWallet);

        $wallet = Wallet::query()->forUser($command->userId)->findOrFail($command->walletId);

        if ($wallet->transactions()->exists() || $wallet->recurringItems()->exists()) {
            throw new DomainException('Không thể xóa ví đang có giao dịch hoặc khoản thu/chi cố định.');
        }

        $wallet->delete();

        return null;
    }
}
