<?php

namespace Wallets\Reporting\Application\Handler;

use App\Models\Loan;
use App\Models\RecurringItem;
use DateTimeImmutable;
use Wallets\Lending\Application\LoanScheduleGenerator;
use Wallets\Reporting\Application\Query\GetFixedExpenseSummary;
use Wallets\Reporting\Domain\FixedExpenseBucketAssembler;
use Wallets\Reporting\Domain\FixedExpenseGranularity;
use Wallets\Reporting\Domain\FixedExpenseOccurrenceProjector;
use Wallets\Reporting\Domain\FixedExpenseWindowBuilder;
use Wallets\Shared\Application\Query;
use Wallets\Shared\Application\QueryHandler;

final class GetFixedExpenseSummaryHandler implements QueryHandler
{
    public function __construct(
        private readonly FixedExpenseWindowBuilder $windowBuilder,
        private readonly FixedExpenseOccurrenceProjector $projector,
        private readonly FixedExpenseBucketAssembler $assembler,
        private readonly LoanScheduleGenerator $scheduleGenerator,
    ) {}

    public function handle(Query $query): mixed
    {
        assert($query instanceof GetFixedExpenseSummary);

        $granularity = FixedExpenseGranularity::isValid($query->granularity)
            ? $query->granularity
            : FixedExpenseGranularity::MONTH;
        $duration = FixedExpenseGranularity::clampDuration($query->duration);
        $customDays = FixedExpenseGranularity::clampCustomDays($query->customDays);
        $today = new DateTimeImmutable('today');

        $requestedStart = $query->start
            ? new DateTimeImmutable($query->start)
            : $today;

        try {
            $buckets = $this->windowBuilder->build(
                $granularity,
                $requestedStart,
                $duration,
                $customDays,
                $today,
            );
        } catch (\InvalidArgumentException) {
            $buckets = $this->windowBuilder->build(
                $granularity,
                $today,
                $duration,
                $customDays,
                $today,
            );
        }

        if ($buckets === []) {
            return $this->emptyPayload($granularity, $duration, $customDays, $today);
        }

        $rangeStart = new DateTimeImmutable($buckets[0]['start']);
        $rangeEnd = new DateTimeImmutable($buckets[array_key_last($buckets)]['end']);

        $recurring = RecurringItem::query()
            ->forUser($query->userId)
            ->where('type', 'expense')
            ->active()
            ->get()
            ->map(fn (RecurringItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'amount' => (float) $item->amount,
                'day_of_month' => (int) $item->day_of_month,
                'effective_from' => $item->effective_from?->toDateString(),
                'ends_at' => $item->ends_at?->toDateString(),
                'is_active' => (bool) $item->is_active,
            ])
            ->all();

        $loans = Loan::query()
            ->forUser($query->userId)
            ->where('is_settled', false)
            ->whereIn('type', ['bank', 'borrow'])
            ->with('customSchedules')
            ->get()
            ->map(function (Loan $loan) {
                if ($loan->type === 'bank') {
                    $this->scheduleGenerator->generate($loan, backfillPast: true);
                    $loan->refresh()->load('customSchedules');
                }

                return [
                    'id' => $loan->id,
                    'name' => $loan->name,
                    'type' => $loan->type,
                    'is_settled' => (bool) $loan->is_settled,
                    'monthly_payment' => $loan->monthly_payment !== null ? (float) $loan->monthly_payment : null,
                    'payment_day' => $loan->payment_day !== null ? (int) $loan->payment_day : null,
                    'started_at' => $loan->started_at?->toDateString(),
                    'term_months' => $loan->term_months !== null ? (int) $loan->term_months : null,
                    'schedules' => $loan->customSchedules
                        ->map(fn ($row) => [
                            'due_date' => $row->due_date?->toDateString() ?? (string) $row->due_date,
                            'payment' => (float) $row->payment,
                            'status' => $row->status,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->all();

        $events = $this->projector->project($recurring, $loans, $rangeStart, $rangeEnd);
        $periods = $this->assembler->assemble($buckets, $events);
        $grandTotal = array_reduce(
            $periods,
            static fn (float $carry, array $period): float => $carry + (float) $period['total'],
            0.0,
        );

        return [
            'granularity' => $granularity,
            'start' => $buckets[0]['start'],
            'duration' => $duration,
            'custom_days' => $customDays,
            'min_start' => $this->windowBuilder->normalizeStart($granularity, $today, $customDays)->format('Y-m-d'),
            'periods' => $periods,
            'grand_total' => $grandTotal,
            'granularity_options' => [
                ['value' => FixedExpenseGranularity::WEEK, 'label' => 'Tuần'],
                ['value' => FixedExpenseGranularity::MONTH, 'label' => 'Tháng'],
                ['value' => FixedExpenseGranularity::YEAR, 'label' => 'Năm'],
                ['value' => FixedExpenseGranularity::CUSTOM, 'label' => 'Tùy chỉnh'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(string $granularity, int $duration, int $customDays, DateTimeImmutable $today): array
    {
        return [
            'granularity' => $granularity,
            'start' => $today->format('Y-m-d'),
            'duration' => $duration,
            'custom_days' => $customDays,
            'min_start' => $this->windowBuilder->normalizeStart($granularity, $today, $customDays)->format('Y-m-d'),
            'periods' => [],
            'grand_total' => 0.0,
            'granularity_options' => [
                ['value' => FixedExpenseGranularity::WEEK, 'label' => 'Tuần'],
                ['value' => FixedExpenseGranularity::MONTH, 'label' => 'Tháng'],
                ['value' => FixedExpenseGranularity::YEAR, 'label' => 'Năm'],
                ['value' => FixedExpenseGranularity::CUSTOM, 'label' => 'Tùy chỉnh'],
            ],
        ];
    }
}
