<?php

namespace Wallets\WalletAccounting\Application\Handler;

use App\Models\TransactionTemplate;
use App\Models\Wallet;
use Wallets\Shared\Application\Command;
use Wallets\Shared\Application\CommandHandler;
use Wallets\WalletAccounting\Application\Command\TransferBetweenWallets;
use Wallets\WalletAccounting\Application\WalletTransferService;

final class TransferBetweenWalletsHandler implements CommandHandler
{
    public function __construct(private readonly WalletTransferService $transfers) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof TransferBetweenWallets);

        $data = $command->data;
        $from = Wallet::query()->forUser($command->userId)->findOrFail($data['from_wallet_id']);
        $to = Wallet::query()->forUser($command->userId)->findOrFail($data['to_wallet_id']);

        $transfer = $this->transfers->record(
            $command->userId,
            $from,
            $to,
            (float) $data['amount'],
            (float) ($data['fee'] ?? 0),
            $data['description'],
            $data['transacted_at'],
            $data['note'] ?? null,
            $data['transaction_template_id'] ?? null,
        );

        if ($command->saveAsTemplate && empty($data['transaction_template_id'])) {
            TransactionTemplate::create([
                'user_id' => $command->userId,
                'name' => $command->templateName ?? $data['description'],
                'type' => 'transfer',
                'amount' => $data['amount'],
                'fee' => $data['fee'] ?? 0,
                'description' => $data['description'],
                'from_wallet_id' => $data['from_wallet_id'],
                'to_wallet_id' => $data['to_wallet_id'],
            ]);
        }

        return $transfer;
    }
}
