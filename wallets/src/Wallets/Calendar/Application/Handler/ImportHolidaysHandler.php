<?php

namespace Wallets\Calendar\Application\Handler;

use App\Models\Holiday;
use Wallets\Calendar\Application\Command\ImportHolidays;
use Wallets\Calendar\Application\ImportHolidaysResult;
use Wallets\Lending\Application\LoanScheduleRecalculator;
use Wallets\Shared\Application\Command;
use Wallets\Shared\Application\CommandHandler;

final class ImportHolidaysHandler implements CommandHandler
{
    public function __construct(private readonly LoanScheduleRecalculator $loanScheduleRecalculator) {}

    public function handle(Command $command): ImportHolidaysResult
    {
        assert($command instanceof ImportHolidays);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $seenDates = [];

        foreach ($command->rows as $index => $row) {
            $lineNumber = $index + 1;

            if (isset($seenDates[$row['date']])) {
                $skipped++;
                $errors[] = "Dòng {$lineNumber}: ngày {$row['date']} trùng trong file (bỏ qua).";

                continue;
            }

            $seenDates[$row['date']] = true;

            try {
                $existing = Holiday::whereDate('date', $row['date'])->first();

                if ($existing) {
                    $existing->update([
                        'name' => $row['name'],
                        'type' => $row['type'],
                    ]);
                    $updated++;
                } else {
                    Holiday::create([
                        'date' => $row['date'],
                        'name' => $row['name'],
                        'type' => $row['type'],
                    ]);
                    $created++;
                }
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "Dòng {$lineNumber}: không thể lưu ngày {$row['date']}.";
            }
        }

        if ($created > 0 || $updated > 0) {
            $this->loanScheduleRecalculator->recalculateUnsettledDailyLoans();
        }

        return new ImportHolidaysResult($created, $updated, $skipped, $errors);
    }
}
