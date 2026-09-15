<?php

namespace Tests\Feature\RecurringPlanning;

use App\Models\RecurringItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecurringItemWithoutWalletTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function can_create_recurring_item_without_wallet(): void
    {
        $user = User::factory()->create(['email' => 'recurring-no-wallet@example.com']);

        $response = $this->actingAs($user)->post(route('recurring-items.store'), [
            'name' => 'Tiền nhà',
            'type' => 'expense',
            'amount' => 5_000_000,
            'day_of_month' => 5,
            'note' => 'Không gắn ví',
        ]);

        $response->assertRedirect(route('recurring-items.index'));
        $this->assertDatabaseHas('recurring_items', [
            'user_id' => $user->id,
            'name' => 'Tiền nhà',
            'wallet_id' => null,
            'amount' => 5_000_000,
        ]);
    }

    #[Test]
    public function index_does_not_require_wallets_prop(): void
    {
        $user = User::factory()->create(['email' => 'recurring-index@example.com']);

        RecurringItem::create([
            'user_id' => $user->id,
            'name' => 'Netflix',
            'type' => 'expense',
            'amount' => 200_000,
            'wallet_id' => null,
            'day_of_month' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('recurring-items.index'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('RecurringItems/Index', false)
            ->has('items', 1)
            ->where('items.0.name', 'Netflix')
            ->missing('wallets')
            ->where('items.0.wallet_id', null)
            ->where('items.0.insufficient_funds', false)
        );
    }

    #[Test]
    public function update_clears_wallet_and_does_not_require_it(): void
    {
        $user = User::factory()->create(['email' => 'recurring-update@example.com']);
        $item = RecurringItem::create([
            'user_id' => $user->id,
            'name' => 'Gym',
            'type' => 'expense',
            'amount' => 500_000,
            'wallet_id' => null,
            'day_of_month' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->put(route('recurring-items.update', $item), [
            'name' => 'Gym Plus',
            'type' => 'expense',
            'amount' => 600_000,
            'day_of_month' => 2,
            'is_active' => true,
        ]);

        $response->assertRedirect(route('recurring-items.index'));
        $this->assertDatabaseHas('recurring_items', [
            'id' => $item->id,
            'name' => 'Gym Plus',
            'wallet_id' => null,
            'amount' => 600_000,
        ]);
    }
}
