<?php

namespace Wallets\Reporting\Application;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TransactionAnalyticsService
{
    public const PERIODS = [
        'day' => ['label' => 'Ngày', 'buckets' => 14, 'range' => '14 ngày gần nhất'],
        'week' => ['label' => 'Tuần', 'buckets' => 8, 'range' => '8 tuần gần nhất'],
        'month' => ['label' => 'Tháng', 'buckets' => 6, 'range' => '6 tháng gần nhất'],
        'year' => ['label' => 'Năm', 'buckets' => 5, 'range' => '5 năm gần nhất'],
    ];

    private const TOP_CATEGORIES = 5;

    public function summary(int $userId, string $period = 'month'): array
    {
        $period = array_key_exists($period, self::PERIODS) ? $period : 'month';
        $config = self::PERIODS[$period];

        $from = $this->rangeStart($period, $config['buckets']);
        $transactions = $this->cashFlowQuery($userId)
            ->where('transacted_at', '>=', $from)
            ->get(['type', 'amount', 'transacted_at', 'category']);

        $series = $this->buildSeries($transactions, $period, $config['buckets']);
        $current = $series->last() ?? $this->emptyBucket($period);
        $prev = $series->count() >= 2 ? $series->values()->get($series->count() - 2) : null;

        $currentRange = $this->currentBucketRange($period);

        return [
            'period' => $period,
            'period_label' => $config['label'],
            'range_label' => $config['range'],
            'series' => $series->values()->all(),
            'current' => $current,
            'expense_vs_prev' => $this->percentChange(
                (float) ($prev['expense'] ?? 0),
                (float) $current['expense']
            ),
            'prev_period_label' => $prev['label_full'] ?? null,
            'top_expense_categories' => $this->buildTopCategories(
                $transactions->filter(function ($t) use ($currentRange) {
                    return $t->type === 'expense'
                        && $t->transacted_at->between($currentRange['start'], $currentRange['end']);
                })
            ),
            'category_scope_label' => $current['label_full'] ?? $config['label'],
            'has_data' => $transactions->isNotEmpty(),
        ];
    }

    private function rangeStart(string $period, int $buckets): Carbon
    {
        return match ($period) {
            'day' => now()->copy()->subDays($buckets - 1)->startOfDay(),
            'week' => now()->copy()->subWeeks($buckets - 1)->startOfWeek(Carbon::MONDAY),
            'year' => now()->copy()->subYears($buckets - 1)->startOfYear(),
            default => now()->copy()->subMonths($buckets - 1)->startOfMonth(),
        };
    }

    private function currentBucketRange(string $period): array
    {
        return match ($period) {
            'day' => [
                'start' => now()->copy()->startOfDay(),
                'end' => now()->copy()->endOfDay(),
            ],
            'week' => [
                'start' => now()->copy()->startOfWeek(Carbon::MONDAY),
                'end' => now()->copy()->endOfWeek(Carbon::SUNDAY),
            ],
            'year' => [
                'start' => now()->copy()->startOfYear(),
                'end' => now()->copy()->endOfYear(),
            ],
            default => [
                'start' => now()->copy()->startOfMonth(),
                'end' => now()->copy()->endOfMonth(),
            ],
        };
    }

    private function buildSeries(Collection $transactions, string $period, int $buckets): Collection
    {
        $series = collect();

        for ($i = $buckets - 1; $i >= 0; $i--) {
            $bucket = $this->bucketMeta($period, $i);
            $inBucket = $transactions->filter(
                fn ($t) => $this->bucketKey($period, $t->transacted_at) === $bucket['key']
            );

            $income = (float) $inBucket->where('type', 'income')->sum('amount');
            $expense = (float) $inBucket->where('type', 'expense')->sum('amount');

            $series->push([
                ...$bucket,
                'income' => $income,
                'expense' => $expense,
                'net' => $income - $expense,
            ]);
        }

        return $series;
    }

    private function bucketMeta(string $period, int $offsetFromEnd): array
    {
        $date = match ($period) {
            'day' => now()->copy()->subDays($offsetFromEnd)->startOfDay(),
            'week' => now()->copy()->subWeeks($offsetFromEnd)->startOfWeek(Carbon::MONDAY),
            'year' => now()->copy()->subYears($offsetFromEnd)->startOfYear(),
            default => now()->copy()->subMonths($offsetFromEnd)->startOfMonth(),
        };

        return match ($period) {
            'day' => [
                'key' => $date->format('Y-m-d'),
                'label' => $date->format('d/m'),
                'label_full' => $date->format('d/m/Y'),
            ],
            'week' => [
                'key' => $date->format('Y-m-d'),
                'label' => $date->format('d/m'),
                'label_full' => 'Tuần '.$date->format('d/m').'–'.$date->copy()->endOfWeek(Carbon::SUNDAY)->format('d/m'),
            ],
            'year' => [
                'key' => $date->format('Y'),
                'label' => $date->format('Y'),
                'label_full' => 'Năm '.$date->format('Y'),
            ],
            default => [
                'key' => $date->format('Y-m'),
                'label' => 'T'.$date->format('n'),
                'label_full' => $date->format('m/Y'),
            ],
        };
    }

    private function bucketKey(string $period, Carbon $date): string
    {
        return match ($period) {
            'day' => $date->format('Y-m-d'),
            'week' => $date->copy()->startOfWeek(Carbon::MONDAY)->format('Y-m-d'),
            'year' => $date->format('Y'),
            default => $date->format('Y-m'),
        };
    }

    private function emptyBucket(string $period): array
    {
        $meta = $this->bucketMeta($period, 0);

        return [...$meta, 'income' => 0, 'expense' => 0, 'net' => 0];
    }

    private function buildTopCategories(Collection $expenses): array
    {
        $grouped = $expenses
            ->groupBy(fn ($t) => trim((string) $t->category) !== '' ? trim($t->category) : 'Khác')
            ->map(fn ($items, $name) => ['name' => $name, 'total' => (float) $items->sum('amount')])
            ->sortByDesc('total')
            ->take(self::TOP_CATEGORIES)
            ->values();

        $colors = ['#f97316', '#ef4444', '#eab308', '#a855f7', '#64748b'];

        return $grouped->map(function ($row, $index) use ($colors) {
            $row['color'] = $colors[$index % count($colors)];

            return $row;
        })->all();
    }

    private function cashFlowQuery(int $userId)
    {
        return Transaction::query()
            ->forUser($userId)
            ->whereIn('type', ['income', 'expense'])
            ->whereNull('wallet_transfer_id');
    }

    private function percentChange(float $previous, float $current): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
