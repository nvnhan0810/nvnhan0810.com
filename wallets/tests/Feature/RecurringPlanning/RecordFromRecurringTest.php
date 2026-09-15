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
use Wallets\RecurringPlanning\Application\RecurringItemService;
use Wallets\RecurringPlanning\Application\RecurringOccurrenceGenerator;
use Wallets\Shared\Application\CommandBus;
use Wallets\WalletAccounting\Application\Command\RecordIncomeExpense;

class RecordFromRecurringTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function recording_from_occurrence_marks_posted_links_tx_and_updates_wallet(): void
    {
        Carbon::setTestNow('2026-07-15');
        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => 1000000,
            'is_active' => true,
        ]);
        $item = RecurringItem::create([
            'user_id' => $user->id,
            'name' => 'Internet',
            'type' => 'expense',
            'amount' => 200000,
            'wallet_id' => $wallet->id,
            'day_of_month' => 15,
            'is_active' => true,
        ]);

        app(RecurringOccurrenceGenerator::class)->ensure($item, Carbon::parse('2026-07-31'));
        $occ = RecurringOccurrence::where('recurring_item_id', $item->id)->firstOrFail();

        $tx = app(CommandBus::class)->dispatch(new RecordIncomeExpense(
            userId: $user->id,
            data: [
                'wallet_id' => $wallet->id,
                'type' => 'expense',
                'amount' => 200000,
                'description' => 'Internet tháng 7',
                'transacted_at' => '2026-07-15',
                'recurring_item_id' => $item->id,
                'recurring_occurrence_id' => $occ->id,
            ],
        ));

        $this->assertSame($item->id, $tx->recurring_item_id);
        $this->assertSame($occ->id, $tx->recurring_occurrence_id);

        $occ->refresh();
        $this->assertSame('posted', $occ->status);
        $this->assertSame($tx->id, $occ->transaction_id);
        $this->assertEquals(800000, (float) $wallet->fresh()->balance);

        $upcoming = app(RecurringItemService::class)->upcoming($user->id, 30);
        $this->assertTrue($upcoming->every(fn ($r) => $r->occurrence_id !== $occ->id));
    }

    #[Test]
    public function overdue_occurrence_shows_in_upcoming(): void
    {
        Carbon::setTestNow('2026-07-10');
        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => 1000000,
            'is_active' => true,
        ]);
        $item = RecurringItem::create([
            'user_id' => $user->id,
            'name' => 'Internet',
            'type' => 'expense',
            'amount' => 200000,
            'wallet_id' => $wallet->id,
            'day_of_month' => 15,
            'is_active' => true,
        ]);

        // Kỳ 15/07 được sinh khi còn tương lai...
        app(RecurringOccurrenceGenerator::class)->ensure($item, Carbon::parse('2026-07-31'));

        // ...rồi tới 20/07 vẫn chưa ghi -> phải là overdue và còn hiện nhắc.
        Carbon::setTestNow('2026-07-20');
        $upcoming = app(RecurringItemService::class)->upcoming($user->id, 3);

        $this->assertCount(1, $upcoming);
        $this->assertSame('2026-07-15', $upcoming->first()->due_date->toDateString());
        $this->assertTrue($upcoming->first()->days_until < 0);

        $occ = RecurringOccurrence::firstOrFail();
        $this->assertSame('overdue', $occ->status);
    }

    #[Test]
    public function updating_item_amount_resyncs_open_occurrences(): void
    {
        Carbon::setTestNow('2026-07-10');
        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'name' => 'Cash',
            'type' => 'cash',
            'balance' => 1000000,
            'is_active' => true,
        ]);
        $item = RecurringItem::create([
            'user_id' => $user->id,
            'name' => 'Internet',
            'type' => 'expense',
            'amount' => 200000,
            'wallet_id' => $wallet->id,
            'day_of_month' => 15,
            'is_active' => true,
        ]);
        app(RecurringOccurrenceGenerator::class)->ensure($item, Carbon::parse('2026-07-31'));

        $item->update(['amount' => 300000]);
        $item->occurrences()
            ->whereIn('status', RecurringOccurrence::openStatuses())
            ->update(['expected_amount' => $item->amount]);

        $this->assertEquals(300000, (float) RecurringOccurrence::firstOrFail()->expected_amount);
    }
}
