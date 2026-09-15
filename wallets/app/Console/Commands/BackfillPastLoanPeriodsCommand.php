<?php

namespace App\Console\Commands;

use App\Models\Loan;
use Illuminate\Console\Command;
use Wallets\Lending\Application\LoanScheduleGenerator;

class BackfillPastLoanPeriodsCommand extends Command
{
    protected $signature = 'loans:backfill-past {--loan= : Chỉ backfill 1 khoản vay}';

    protected $description = 'Đánh dấu các kỳ đến hạn ≤ hôm nay là đã trả (không ghi giao dịch ví)';

    public function handle(LoanScheduleGenerator $generator): int
    {
        $query = Loan::query()->where('type', 'bank')->where('is_settled', false);

        if ($loanId = $this->option('loan')) {
            $query->whereKey($loanId);
        }

        $count = 0;
        $marked = 0;

        foreach ($query->get() as $loan) {
            $before = (int) $loan->months_paid;
            $generator->backfillPastPeriods($loan->fresh());
            $after = (int) $loan->fresh()->months_paid;
            $marked += max(0, $after - $before);
            $count++;
        }

        $this->info("Đã kiểm tra {$count} khoản vay; months_paid tăng thêm {$marked}.");

        return self::SUCCESS;
    }
}
