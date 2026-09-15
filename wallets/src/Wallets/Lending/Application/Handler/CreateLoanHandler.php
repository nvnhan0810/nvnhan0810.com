<?php

namespace Wallets\Lending\Application\Handler;

use App\Models\Loan;
use App\Models\LoanCustomSchedule;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Wallets\Lending\Application\Command\CreateLoan;
use Wallets\Lending\Application\LoanScheduleGenerator;
use Wallets\Lending\Application\LoanWalletService;
use Wallets\Shared\Application\Command;
use Wallets\Shared\Application\CommandHandler;

final class CreateLoanHandler implements CommandHandler
{
    public function __construct(
        private readonly LoanWalletService $loanWallet,
        private readonly LoanScheduleGenerator $scheduleGenerator,
    ) {}

    public function handle(Command $command): mixed
    {
        assert($command instanceof CreateLoan);

        $data = $command->data;
        $method = $data['interest_calculation_method'] ?? 'monthly';
        $isCustom = $method === 'custom';
        $hasPreviewSchedule = $command->customSchedule !== [];

        if ($command->recordCashFlow && empty($data['wallet_id'])) {
            throw new \InvalidArgumentException('wallet_id required when recording cash flow');
        }

        if (! empty($data['wallet_id'])) {
            Wallet::query()->forUser($command->userId)->findOrFail($data['wallet_id']);
        }

        $errors = [];

        $loan = DB::transaction(function () use ($command, $data, $isCustom, $hasPreviewSchedule, &$errors) {
            $loan = Loan::create(array_filter([
                'user_id' => $command->userId,
                'type' => $data['type'],
                'name' => $data['name'],
                'principal_amount' => $data['principal_amount'],
                'started_at' => $data['started_at'],
                'wallet_id' => $data['wallet_id'] ?? null,
                'interest_rate' => $data['interest_rate'] ?? null,
                'interest_calculation_method' => $data['interest_calculation_method'] ?? 'monthly',
                'term_months' => $data['term_months'] ?? null,
                'months_paid' => $data['months_paid'] ?? 0,
                'monthly_payment' => $data['monthly_payment'] ?? null,
                'collection_fee' => $data['collection_fee'] ?? 0,
                'payment_day' => $data['payment_day'] ?? null,
            ], fn ($v) => $v !== null));

            if ($isCustom || $hasPreviewSchedule) {
                $errors = $this->persistCustomSchedule($loan, (float) ($data['monthly_payment'] ?? 0), $command->customSchedule);
                $this->scheduleGenerator->backfillPastPeriods($loan->fresh());
            } else {
                $this->scheduleGenerator->generate($loan->fresh());
            }

            // Chỉ ghi giao dịch vào ví khi ngày bắt đầu là hôm nay (ví và khoản vay độc lập).
            if ($command->recordCashFlow
                && ! empty($data['wallet_id'])
                && Carbon::parse($data['started_at'])->isToday()) {
                $wallet = Wallet::query()->forUser($command->userId)->findOrFail($data['wallet_id']);
                $this->loanWallet->recordCreation($loan, $wallet, $command->receivedAmount);
            }

            return $loan;
        });

        return ['loan' => $loan, 'errors' => $errors];
    }

    /**
     * @return array<int, string>
     */
    private function persistCustomSchedule(Loan $loan, float $monthlyPayment, array $rows): array
    {
        $errors = [];

        foreach ($rows as $idx => $row) {
            if (! isset($row['payment'], $row['principal'], $row['interest'])) {
                $errors[] = 'Dòng '.($idx + 1).' thiếu dữ liệu.';

                continue;
            }

            $payment = (float) $row['payment'];
            $principal = (float) $row['principal'];
            $interest = (float) $row['interest'];
            $fee = (float) ($row['fee'] ?? 0);
            $isLast = $idx === count($rows) - 1;

            if (round($principal + $interest + $fee, 0) !== round($payment, 0)) {
                $errors[] = 'Dòng '.($idx + 1).': Gốc + Lãi + Phí phải bằng Tổng trả.';

                continue;
            }

            if (! $isLast && round($payment, 0) > round($monthlyPayment, 0)) {
                $errors[] = 'Dòng '.($idx + 1).': Tổng trả vượt số tiền hàng tháng.';

                continue;
            }

            LoanCustomSchedule::create([
                'loan_id' => $loan->id,
                'user_id' => $loan->user_id,
                'month_index' => $row['month_index'] ?? ($idx + 1),
                'due_date' => $row['paid_at'] ?? null,
                'payment' => $payment,
                'principal' => $principal,
                'interest' => $interest,
                'fee' => $fee,
                'remaining_principal' => $row['remaining_principal'] ?? 0,
                'note' => $row['note'] ?? null,
            ]);
        }

        return $errors;
    }
}
