<?php

namespace Wallets\WalletAccounting\Application\Handler;

use App\Models\Wallet;
use Wallets\Shared\Application\Command;
use Wallets\Shared\Application\CommandHandler;
use Wallets\WalletAccounting\Application\Command\UpdateWallet;

final class UpdateWalletHandler implements CommandHandler
{
    public function handle(Command $command): mixed
    {
        assert($command instanceof UpdateWallet);

        $wallet = Wallet::query()->forUser($command->userId)->findOrFail($command->walletId);
        $wallet->update($command->data);

        return $wallet;
    }
}
