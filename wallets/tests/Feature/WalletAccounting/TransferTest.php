<?php

namespace Tests\Feature\WalletAccounting;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Wallets\Shared\Application\CommandBus;
use Wallets\WalletAccounting\Application\Command\TransferBetweenWallets;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function transfer_updates_balances_for_owner(): void
    {
        $user = User::factory()->create(['email' => 'transfer-owner@example.com']);
        $from = Wallet::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => 1000000,
            'is_active' => true,
        ]);
        $to = Wallet::create([
            'user_id' => $user->id,
            'name' => 'Bank',
            'type' => 'bank',
            'balance' => 0,
            'is_active' => true,
        ]);

        app(CommandBus::class)->dispatch(new TransferBetweenWallets(
            userId: $user->id,
            data: [
                'from_wallet_id' => $from->id,
                'to_wallet_id' => $to->id,
                'amount' => 250000,
                'fee' => 0,
                'description' => 'Move',
                'transacted_at' => now()->toDateString(),
            ],
        ));

        $this->assertEquals(750000, (float) $from->fresh()->balance);
        $this->assertEquals(250000, (float) $to->fresh()->balance);
    }
}
