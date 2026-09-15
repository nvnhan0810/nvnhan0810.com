<?php

namespace App\Console\Commands;

use App\Models\Loan;
use Illuminate\Console\Command;
use Wallets\Lending\Application\LoanScheduleGenerator;

class GenerateLoanPeriodsCommand extends Command
{
    protected $signature = 'loans:generate-periods {--loan= : Chỉ sinh cho 1 khoản vay} {--no-backfill : Không tự đánh dấu kỳ cũ là đã trả}';

    protected $description = 'Sinh/điền lịch kỳ trả (loan_custom_schedules) cho khoản vay ngân hàng';

    public function handle(LoanScheduleGenerator $generator): int
    {
        $backfill = ! $this->option('no-backfill');

        $query = Loan::query()->where('type', 'bank')->where('is_settled', false);

        if ($loanId = $this->option('loan')) {
            $query->whereKey($loanId);
        }

        $count = 0;
        foreach ($query->get() as $loan) {
            $generator->generate($loan, $backfill);
            $count++;
        }

        $this->info("Đã sinh kỳ cho {$count} khoản vay".($backfill ? ' (backfill kỳ cũ = đã trả).' : '.'));

        return self::SUCCESS;
    }
}
