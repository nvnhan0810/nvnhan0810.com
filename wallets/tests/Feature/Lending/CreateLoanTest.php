<?php

namespace Tests\Feature\Lending;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Wallets\Lending\Application\Command\CreateLoan;
use Wallets\Shared\Application\CommandBus;

class CreateLoanTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creating_loan_records_cash_flow_on_wallet(): void
    {
        $user = User::factory()->create(['email' => 'loan-owner@example.com']);
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => 0,
            'is_active' => true,
        ]);

        $result = app(CommandBus::class)->dispatch(new CreateLoan(
            userId: $user->id,
            data: [
                'type' => 'borrow',
                'name' => 'Friend loan',
                'principal_amount' => 500000,
                'started_at' => now()->toDateString(),
                'wallet_id' => $wallet->id,
            ],
            recordCashFlow: true,
        ));

        $this->assertNotEmpty($result['loan']->id);
        $this->assertEquals(500000, (float) $wallet->fresh()->balance);
        $this->assertDatabaseHas('loans', [
            'user_id' => $user->id,
            'name' => 'Friend loan',
        ]);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'wallet_id' => $wallet->id,
            'type' => 'income',
            'amount' => 500000,
        ]);
    }
}
