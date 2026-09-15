<?php

namespace Wallets\Lending\Application\Handler;

use App\Models\Loan;
use App\Models\Payment;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Wallets\Lending\Application\AmortizationService;
use Wallets\Lending\Application\Command\RecordLoanPayment;
use Wallets\Lending\Application\LoanPaymentScheduleService;
use Wallets\Lending\Application\LoanScheduleStateService;
use Wallets\Lending\Application\LoanWalletService;
use Wallets\Shared\Application\Command;
use Wallets\Shared\Application\CommandHandler;

final class RecordLoanPaymentHandler implements CommandHandler
{
    public function __construct(
        private readonly LoanWalletService $loanWallet,
        private readonly LoanPaymentScheduleService $paymentSchedule,
        private readonly AmortizationService $amortization,
        private readonly LoanScheduleStateService $scheduleState,
    ) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof RecordLoanPayment);

        $data = $command->data;
        $wasEarly = false;

        DB::transaction(function () use ($command, $data, &$wasEarly) {
            $loan = Loan::query()->forUser($command->userId)->findOrFail($data['loan_id']);
            $wallet = Wallet::query()->forUser($command->userId)->findOrFail($data['wallet_id']);
            $paidAt = Carbon::parse($data['paid_at']);

            $periodMeta = ['kind' => Payment::KIND_PERIOD, 'reduces_principal' => true];
            if ($loan->type === 'bank') {
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
                $resolved = $this->paymentSchedule->resolvePeriodForPayment($loan, $paidAt, $schedule);
                $periodMeta = [
                    'kind' => $resolved['kind'],
                    'period_due_date' => $resolved['period_due_date']->toDateString(),
                    'schedule_month_index' => $resolved['schedule_month_index'],
                    'reduces_principal' => $resolved['reduces_principal'],
                ];
            }

            $payment = Payment::create([
                'loan_id' => $data['loan_id'],
                'amount' => $data['amount'],
                'paid_at' => $data['paid_at'],
                'note' => $data['note'] ?? null,
                ...$periodMeta,
            ]);

            $this->loanWallet->recordPayment($loan, $payment, $wallet);

            if ($loan->type === 'bank') {
                $payment->setRelation('loan', $loan);
                $this->scheduleState->linkPaymentToPeriod(
                    $payment,
                    isset($data['period_id']) ? (int) $data['period_id'] : null,
                );
            }

            if (! $loan->wallet_id) {
                $loan->update(['wallet_id' => $wallet->id]);
            }

            $wasEarly = $payment->isEarly();
        });

        return ['was_early' => $wasEarly];
    }
}
