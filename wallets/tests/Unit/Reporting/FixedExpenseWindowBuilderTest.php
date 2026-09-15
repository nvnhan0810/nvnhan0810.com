<?php

namespace Tests\Unit\Reporting;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Wallets\Reporting\Domain\FixedExpenseGranularity;
use Wallets\Reporting\Domain\FixedExpenseWindowBuilder;

class FixedExpenseWindowBuilderTest extends TestCase
{
    #[Test]
    public function builds_five_month_buckets_from_current_month(): void
    {
        $today = new DateTimeImmutable('2026-08-31');
        $builder = new FixedExpenseWindowBuilder;

        $buckets = $builder->build(
            FixedExpenseGranularity::MONTH,
            new DateTimeImmutable('2026-08-01'),
            5,
            FixedExpenseGranularity::DEFAULT_CUSTOM_DAYS,
            $today,
        );

        $this->assertCount(5, $buckets);
        $this->assertSame(1, $buckets[0]['index']);
        $this->assertSame('2026-08-01', $buckets[0]['start']);
        $this->assertSame('2026-08-31', $buckets[0]['end']);
        $this->assertSame('Tháng 1 (01/08/2026 – 31/08/2026)', $buckets[0]['label']);
        $this->assertSame('2026-12-01', $buckets[4]['start']);
        $this->assertSame('2026-12-31', $buckets[4]['end']);
    }

    #[Test]
    public function builds_week_buckets_monday_to_sunday(): void
    {
        $today = new DateTimeImmutable('2026-08-31'); // Monday
        $builder = new FixedExpenseWindowBuilder;

        $buckets = $builder->build(
            FixedExpenseGranularity::WEEK,
            new DateTimeImmutable('2026-08-31'),
            3,
            FixedExpenseGranularity::DEFAULT_CUSTOM_DAYS,
            $today,
        );

        $this->assertCount(3, $buckets);
        $this->assertSame('2026-08-31', $buckets[0]['start']);
        $this->assertSame('2026-09-06', $buckets[0]['end']);
        $this->assertSame('Tuần 1 (31/08/2026 – 06/09/2026)', $buckets[0]['label']);
        $this->assertSame('2026-09-14', $buckets[2]['start']);
    }

    #[Test]
    public function rejects_start_before_current_period(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new FixedExpenseWindowBuilder)->build(
            FixedExpenseGranularity::MONTH,
            new DateTimeImmutable('2026-07-01'),
            5,
            30,
            new DateTimeImmutable('2026-08-15'),
        );
    }

    #[Test]
    public function builds_custom_day_buckets(): void
    {
        $today = new DateTimeImmutable('2026-08-31');
        $buckets = (new FixedExpenseWindowBuilder)->build(
            FixedExpenseGranularity::CUSTOM,
            new DateTimeImmutable('2026-08-31'),
            2,
            10,
            $today,
        );

        $this->assertCount(2, $buckets);
        $this->assertSame('2026-08-31', $buckets[0]['start']);
        $this->assertSame('2026-09-09', $buckets[0]['end']);
        $this->assertSame('Kỳ 1 (31/08/2026 – 09/09/2026)', $buckets[0]['label']);
        $this->assertSame('2026-09-10', $buckets[1]['start']);
        $this->assertSame('2026-09-19', $buckets[1]['end']);
    }

    #[Test]
    public function clamps_duration_to_allowed_range(): void
    {
        $today = new DateTimeImmutable('2026-01-01');
        $buckets = (new FixedExpenseWindowBuilder)->build(
            FixedExpenseGranularity::YEAR,
            new DateTimeImmutable('2026-01-01'),
            99,
            30,
            $today,
        );

        $this->assertCount(FixedExpenseGranularity::MAX_DURATION, $buckets);
        $this->assertSame('Năm 1 (01/01/2026 – 31/12/2026)', $buckets[0]['label']);
    }
}
