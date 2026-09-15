<?php

namespace Tests\Feature\Lending;

use App\Models\Loan;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreateLendLoanTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function lend_loan_ignores_stale_custom_interest_method_from_form(): void
    {
        Carbon::setTestNow('2026-08-31');

        $user = User::factory()->create(['email' => 'lend@example.com']);
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => 10_000_000,
            'is_active' => true,
        ]);

        // UI giữ interest_calculation_method=custom sau khi đổi từ bank → lend
        $response = $this->actingAs($user)->post(route('loans.store'), [
            'type' => 'lend',
            'name' => 'Bạn B',
            'principal_amount' => 2_000_000,
            'started_at' => '31/08/2026',
            'wallet_id' => $wallet->id,
            'record_cash_flow' => true,
            'received_amount' => 2_000_000,
            'interest_calculation_method' => 'custom',
            'interest_rate' => 10,
            'term_months' => 12,
            'monthly_payment' => 0,
            'custom_schedule' => [],
        ]);

        $response->assertRedirect(route('loans.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('loans', [
            'user_id' => $user->id,
            'type' => 'lend',
            'name' => 'Bạn B',
            'principal_amount' => 2_000_000,
        ]);

        $loan = Loan::query()->where('name', 'Bạn B')->first();
        $this->assertNotNull($loan);
        $this->assertSame(0, $loan->customSchedules()->count());
    }

    #[Test]
    public function borrow_loan_does_not_require_custom_schedule(): void
    {
        Carbon::setTestNow('2026-08-31');

        $user = User::factory()->create(['email' => 'borrow@example.com']);

        $response = $this->actingAs($user)->post(route('loans.store'), [
            'type' => 'borrow',
            'name' => 'Bạn A',
            'principal_amount' => 1_000_000,
            'started_at' => '31/08/2026',
            'record_cash_flow' => false,
            'interest_calculation_method' => 'custom',
            'custom_schedule' => [],
        ]);

        $response->assertRedirect(route('loans.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('loans', [
            'user_id' => $user->id,
            'type' => 'borrow',
            'name' => 'Bạn A',
        ]);
    }
}
