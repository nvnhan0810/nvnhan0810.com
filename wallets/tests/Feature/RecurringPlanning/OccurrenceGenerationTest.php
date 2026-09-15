<?php

namespace Tests\Feature\RecurringPlanning;

use App\Models\RecurringItem;
use App\Models\RecurringOccurrence;
use App\Models\User;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Wallets\RecurringPlanning\Application\RecurringOccurrenceGenerator;

class OccurrenceGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function makeItem(User $user, array $overrides = []): RecurringItem
    {
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => 5000000,
            'is_active' => true,
        ]);

        return RecurringItem::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Internet',
            'type' => 'expense',
            'amount' => 200000,
            'wallet_id' => $wallet->id,
            'day_of_month' => 15,
            'is_active' => true,
        ], $overrides));
    }

    #[Test]
    public function generates_current_and_future_months_without_backfilling_past(): void
    {
        Carbon::setTestNow('2026-07-10');
        $user = User::factory()->create();
        $item = $this->makeItem($user);

        app(RecurringOccurrenceGenerator::class)->ensure($item, Carbon::parse('2026-09-30'));

        $dues = RecurringOccurrence::where('recurring_item_id', $item->id)
            ->orderBy('due_date')->pluck('due_date')->map->toDateString();

        $this->assertSame(['2026-07-15', '2026-08-15', '2026-09-15'], $dues->all());
    }

    #[Test]
    public function respects_ends_at(): void
    {
        Carbon::setTestNow('2026-07-10');
        $user = User::factory()->create();
        $item = $this->makeItem($user, ['ends_at' => '2026-08-01']);

        app(RecurringOccurrenceGenerator::class)->ensure($item, Carbon::parse('2026-09-30'));

        $dues = RecurringOccurrence::where('recurring_item_id', $item->id)
            ->pluck('due_date')->map->toDateString();

        $this->assertSame(['2026-07-15'], $dues->all());
    }

    #[Test]
    public function respects_future_effective_from(): void
    {
        Carbon::setTestNow('2026-07-10');
        $user = User::factory()->create();
        $item = $this->makeItem($user, ['effective_from' => '2026-08-05']);

        app(RecurringOccurrenceGenerator::class)->ensure($item, Carbon::parse('2026-09-30'));

        $dues = RecurringOccurrence::where('recurring_item_id', $item->id)
            ->orderBy('due_date')->pluck('due_date')->map->toDateString();

        $this->assertSame(['2026-08-15', '2026-09-15'], $dues->all());
    }

    #[Test]
    public function is_idempotent(): void
    {
        Carbon::setTestNow('2026-07-10');
        $user = User::factory()->create();
        $item = $this->makeItem($user);
        $generator = app(RecurringOccurrenceGenerator::class);

        $generator->ensure($item, Carbon::parse('2026-09-30'));
        $generator->ensure($item, Carbon::parse('2026-09-30'));

        $this->assertSame(3, RecurringOccurrence::where('recurring_item_id', $item->id)->count());
    }
}
