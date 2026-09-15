<?php

namespace Wallets\Lending\Application\Handler;

use App\Models\Loan;
use Wallets\Lending\Application\AmortizationService;
use Wallets\Lending\Application\LoanPaymentScheduleService;
use Wallets\Lending\Application\LoanScheduleGenerator;
use Wallets\Lending\Application\Query\GetLoanDetail;
use Wallets\Shared\Application\Query;
use Wallets\Shared\Application\QueryHandler;

final class GetLoanDetailHandler implements QueryHandler
{
    public function __construct(
        private readonly LoanPaymentScheduleService $paymentSchedule,
        private readonly AmortizationService $amortization,
        private readonly LoanScheduleGenerator $scheduleGenerator,
    ) {}

    public function handle(Query $query): mixed
    {
        assert($query instanceof GetLoanDetail);

        $loan = Loan::query()
            ->forUser($query->userId)
            ->with(['payments.transaction', 'wallet', 'customSchedules'])
            ->findOrFail($query->loanId);

        $schedule = collect([]);
        $monthsPassed = 0;
        $timeline = collect([]);

        if ($loan->type === 'bank') {
            $this->scheduleGenerator->backfillPastPeriods($loan);
            $loan->refresh()->load(['payments.transaction', 'wallet', 'customSchedules']);

            $schedule = $this->amortization->calculate(
                $loan->id,
                $loan->principal_amount,
                $loan->interest_rate,
                $loan->term_months,
                $loan->started_at,
                $loan->monthly_payment,
                $loan->interest_calculation_method ?? 'monthly',
                $loan->payment_day,
                $loan->collection_fee ?? 0,
            );

            if ($schedule->isNotEmpty()) {
                $monthsPassed = $this->paymentSchedule->effectiveMonthsPaid($loan, $schedule, $loan->payments);
                $maxIndex = $schedule->max('month_index');

                $loan->remaining_months = max(0, ($loan->term_months ?? $maxIndex) - $monthsPassed);
                $loan->remaining_principal = $this->paymentSchedule->remainingPrincipalAt($loan, $schedule, $monthsPassed);
                $loan->remaining_interest = $schedule->where('month_index', '>', $monthsPassed)->sum('interest');
                $timeline = $this->paymentSchedule->buildTimeline($loan, $schedule, $loan->payments, $monthsPassed);
            } else {
                $loan->remaining_months = $loan->term_months;
                $loan->remaining_principal = $loan->principal_amount;
                $loan->remaining_interest = 0;
            }
        }

        $paymentDay = $loan->isBankLoan() ? $this->paymentSchedule->paymentDay($loan) : null;
        $timeline = $this->paymentSchedule->serializeTimeline($timeline);

        return compact('loan', 'schedule', 'monthsPassed', 'timeline', 'paymentDay');
    }
}
