<?php

namespace Wallets\WalletAccounting\Application\Handler;

use App\Models\Wallet;
use Wallets\Shared\Application\Command;
use Wallets\Shared\Application\CommandHandler;
use Wallets\WalletAccounting\Application\Command\CreateWallet;

final class CreateWalletHandler implements CommandHandler
{
    public function handle(Command $command): mixed
    {
        assert($command instanceof CreateWallet);

        return Wallet::create(['user_id' => $command->userId, ...$command->data]);
    }
}
