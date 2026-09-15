<?php

namespace Wallets\Lending\Application;

use App\Models\Loan;
use App\Models\LoanCustomSchedule;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LoanScheduleStateService
{
    /**
     * Cập nhật trạng thái kỳ chưa trả theo mốc hôm nay: due (đúng ngày), overdue (quá hạn), pending (tương lai).
     */
    public function transitionStatuses(?int $userId = null): void
    {
        $today = Carbon::today()->toDateString();
        $open = [LoanCustomSchedule::STATUS_PENDING, LoanCustomSchedule::STATUS_DUE, LoanCustomSchedule::STATUS_OVERDUE];

        $base = fn () => LoanCustomSchedule::query()
            ->whereIn('status', $open)
            ->whereNotNull('due_date')
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId));

        $base()->whereDate('due_date', '<', $today)->update(['status' => LoanCustomSchedule::STATUS_OVERDUE]);
        $base()->whereDate('due_date', '=', $today)->update(['status' => LoanCustomSchedule::STATUS_DUE]);
        $base()->whereDate('due_date', '>', $today)->update(['status' => LoanCustomSchedule::STATUS_PENDING]);
    }

    /**
     * Gán 1 lần trả vào kỳ tương ứng và đánh dấu đã trả. Ưu tiên period_id chỉ định,
     * rồi tới kỳ khớp schedule_month_index của payment, cuối cùng là kỳ mở cũ nhất.
     */
    public function linkPaymentToPeriod(Payment $payment, ?int $periodId = null): ?LoanCustomSchedule
    {
        $loan = $payment->loan;
        if (! $loan || $loan->type !== 'bank') {
            return null;
        }

        $period = $this->resolvePeriod($loan, $payment, $periodId);

        if (! $period) {
            return null;
        }

        $period->markPaid($payment);
        $this->syncMonthsPaid($loan);

        return $period;
    }

    private function resolvePeriod(Loan $loan, Payment $payment, ?int $periodId): ?LoanCustomSchedule
    {
        $query = LoanCustomSchedule::query()->where('loan_id', $loan->id);

        if ($periodId) {
            return (clone $query)->whereKey($periodId)->first();
        }

        if ($payment->schedule_month_index) {
            $match = (clone $query)
                ->where('month_index', $payment->schedule_month_index)
                ->whereIn('status', $this->openStatuses())
                ->first();

            if ($match) {
                return $match;
            }
        }

        return (clone $query)
            ->whereIn('status', $this->openStatuses())
            ->orderBy('month_index')
            ->first();
    }

    public function syncMonthsPaid(Loan $loan): void
    {
        $paid = LoanCustomSchedule::query()
            ->where('loan_id', $loan->id)
            ->where('status', LoanCustomSchedule::STATUS_PAID)
            ->count();

        $loan->update(['months_paid' => max((int) $loan->months_paid, $paid)]);
    }

    /**
     * Kỳ đến hạn đúng hôm nay, chưa trả, chưa gửi Telegram hôm nay.
     *
     * @return Collection<int, LoanCustomSchedule>
     */
    public function dueTodayUnremindedPeriods(int $userId): Collection
    {
        $today = Carbon::today();

        return LoanCustomSchedule::query()
            ->with('loan')
            ->where('user_id', $userId)
            ->whereIn('status', [LoanCustomSchedule::STATUS_DUE, LoanCustomSchedule::STATUS_OVERDUE])
            ->whereDate('due_date', '<=', $today->toDateString())
            ->where(function ($q) use ($today) {
                $q->whereNull('reminded_telegram_at')
                    ->orWhereDate('reminded_telegram_at', '<', $today->toDateString());
            })
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Kỳ sắp/đang tới hạn cho dashboard: mở, đến hạn trong vòng $withinDays hoặc đã quá hạn.
     *
     * @return Collection<int, LoanCustomSchedule>
     */
    public function upcomingPeriods(int $userId, int $withinDays): Collection
    {
        $until = Carbon::today()->addDays($withinDays)->toDateString();

        return LoanCustomSchedule::query()
            ->with('loan.wallet')
            ->where('user_id', $userId)
            ->whereIn('status', $this->openStatuses())
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $until)
            ->orderBy('due_date')
            ->get();
    }

    /**
     * @return array<int, string>
     */
    private function openStatuses(): array
    {
        return [LoanCustomSchedule::STATUS_PENDING, LoanCustomSchedule::STATUS_DUE, LoanCustomSchedule::STATUS_OVERDUE];
    }
}
