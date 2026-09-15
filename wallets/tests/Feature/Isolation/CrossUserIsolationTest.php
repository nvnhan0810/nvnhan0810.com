<?php

namespace Tests\Feature\Isolation;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CrossUserIsolationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_cannot_view_another_users_wallet(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $intruder = User::factory()->create(['email' => 'intruder@example.com']);

        $wallet = Wallet::create([
            'user_id' => $owner->id,
            'name' => 'Secret',
            'type' => 'cash',
            'balance' => 1000,
            'is_active' => true,
        ]);

        $this->actingAs($intruder)
            ->get(route('wallets.edit', $wallet))
            ->assertNotFound();
    }

    #[Test]
    public function wallet_index_only_shows_own_wallets(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        Wallet::create(['user_id' => $a->id, 'name' => 'A Wallet', 'type' => 'cash', 'balance' => 1, 'is_active' => true]);
        Wallet::create(['user_id' => $b->id, 'name' => 'B Wallet', 'type' => 'cash', 'balance' => 1, 'is_active' => true]);

        $this->actingAs($a)
            ->get(route('wallets.index'))
            ->assertOk()
            ->assertSee('A Wallet')
            ->assertDontSee('B Wallet');
    }
}
