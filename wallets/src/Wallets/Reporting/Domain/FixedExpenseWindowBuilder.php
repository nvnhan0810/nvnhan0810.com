<?php

namespace Wallets\Reporting\Domain;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Xây các kỳ (bucket) từ mốc bắt đầu trở đi. Không cho chọn kỳ trước kỳ hiện tại.
 */
final class FixedExpenseWindowBuilder
{
    /**
     * @return list<array{index:int,key:string,label:string,start:string,end:string}>
     */
    public function build(
        string $granularity,
        DateTimeImmutable $start,
        int $duration,
        int $customDays,
        DateTimeImmutable $today,
    ): array {
        if (! FixedExpenseGranularity::isValid($granularity)) {
            throw new InvalidArgumentException('Invalid granularity.');
        }

        $duration = FixedExpenseGranularity::clampDuration($duration);
        $customDays = FixedExpenseGranularity::clampCustomDays($customDays);
        $start = $this->normalizeStart($granularity, $start, $customDays);
        $minStart = $this->normalizeStart($granularity, $today, $customDays);

        if ($start < $minStart) {
            throw new InvalidArgumentException('Start must be at or after the current period.');
        }

        $buckets = [];
        $cursor = $start;

        for ($i = 1; $i <= $duration; $i++) {
            [$bucketStart, $bucketEnd] = $this->bucketRange($granularity, $cursor, $customDays);
            $buckets[] = [
                'index' => $i,
                'key' => $bucketStart->format('Y-m-d'),
                'label' => $this->label($granularity, $i, $bucketStart, $bucketEnd),
                'start' => $bucketStart->format('Y-m-d'),
                'end' => $bucketEnd->format('Y-m-d'),
            ];
            $cursor = $bucketEnd->modify('+1 day');
        }

        return $buckets;
    }

    public function normalizeStart(string $granularity, DateTimeImmutable $date, int $customDays = FixedExpenseGranularity::DEFAULT_CUSTOM_DAYS): DateTimeImmutable
    {
        $date = $date->setTime(0, 0, 0);

        return match ($granularity) {
            FixedExpenseGranularity::WEEK => $this->startOfWeek($date),
            FixedExpenseGranularity::MONTH => $date->modify('first day of this month'),
            FixedExpenseGranularity::YEAR => $date->setDate((int) $date->format('Y'), 1, 1),
            FixedExpenseGranularity::CUSTOM => $date,
            default => $date->modify('first day of this month'),
        };
    }

    /**
     * @return array{0:DateTimeImmutable,1:DateTimeImmutable}
     */
    private function bucketRange(string $granularity, DateTimeImmutable $start, int $customDays): array
    {
        $start = $start->setTime(0, 0, 0);

        return match ($granularity) {
            FixedExpenseGranularity::WEEK => [$start, $start->modify('+6 days')],
            FixedExpenseGranularity::MONTH => [
                $start,
                $start->modify('last day of this month'),
            ],
            FixedExpenseGranularity::YEAR => [
                $start,
                $start->setDate((int) $start->format('Y'), 12, 31),
            ],
            FixedExpenseGranularity::CUSTOM => [
                $start,
                $start->add(new DateInterval('P'.max(0, $customDays - 1).'D')),
            ],
            default => [$start, $start->modify('last day of this month')],
        };
    }

    private function startOfWeek(DateTimeImmutable $date): DateTimeImmutable
    {
        $date = $date->setTime(0, 0, 0);
        $dow = (int) $date->format('N'); // 1=Mon … 7=Sun

        return $date->modify('-'.($dow - 1).' days');
    }

    private function label(string $granularity, int $index, DateTimeImmutable $start, DateTimeImmutable $end): string
    {
        $range = $this->formatVi($start).' – '.$this->formatVi($end);

        return match ($granularity) {
            FixedExpenseGranularity::WEEK => "Tuần {$index} ({$range})",
            FixedExpenseGranularity::MONTH => "Tháng {$index} ({$range})",
            FixedExpenseGranularity::YEAR => "Năm {$index} ({$range})",
            FixedExpenseGranularity::CUSTOM => "Kỳ {$index} ({$range})",
            default => "Kỳ {$index} ({$range})",
        };
    }

    private function formatVi(DateTimeImmutable $date): string
    {
        return $date->format('d/m/Y');
    }
}
