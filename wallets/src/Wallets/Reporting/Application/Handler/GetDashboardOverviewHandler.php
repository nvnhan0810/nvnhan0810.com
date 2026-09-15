<?php

namespace Wallets\Reporting\Application\Handler;

use App\Models\Loan;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Support\Collection;
use Wallets\Lending\Application\LoanPaymentReminderService;
use Wallets\RecurringPlanning\Application\RecurringItemService;
use Wallets\Reporting\Application\Query\GetDashboardOverview;
use Wallets\Reporting\Application\TransactionAnalyticsService;
use Wallets\Shared\Application\Query;
use Wallets\Shared\Application\QueryHandler;

final class GetDashboardOverviewHandler implements QueryHandler
{
    public function __construct(
        private readonly RecurringItemService $recurringService,
        private readonly LoanPaymentReminderService $loanReminders,
        private readonly TransactionAnalyticsService $analytics,
    ) {}

    public function handle(Query $query): mixed
    {
        assert($query instanceof GetDashboardOverview);

        $alertDays = Setting::recurringAlertDays($query->userId);
        $upcomingLoanPayments = $this->loanReminders->upcomingLoanPayments($query->userId, $alertDays);
        $upcomingRecurring = $this->recurringService->upcoming($query->userId, $alertDays);
        $upcomingReminders = $this->buildUpcomingReminders($upcomingLoanPayments, $upcomingRecurring);

        $allActiveWallets = Wallet::query()
            ->forUser($query->userId)
            ->where('is_active', true)
            ->get();

        $walletsCount = $allActiveWallets->count();
        
        $totalBalance = $allActiveWallets->where('type', '!=', 'credit_card')->sum('balance');

        $wallets = $allActiveWallets
            ->where('is_pinned', true)
            ->sortBy('order')
            ->values();

        $creditCardDebt = $allActiveWallets->where('type', 'credit_card')->sum('outstanding_balance');
        // Loans debt will be added in the controller
        $totalDebt = $creditCardDebt;

        $recentTransactions = Transaction::query()
            ->forUser($query->userId)
            ->with('wallet')
            ->orderByDesc('transacted_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $unsettledLoansCount = Loan::query()->forUser($query->userId)->where('is_settled', false)->count();

        $chartPeriod = $query->chartPeriod;
        if (! array_key_exists($chartPeriod, TransactionAnalyticsService::PERIODS)) {
            $chartPeriod = 'month';
        }
        $cashFlowStats = $this->analytics->summary($query->userId, $chartPeriod);

        return compact(
            'alertDays',
            'upcomingReminders',
            'wallets',
            'walletsCount',
            'totalBalance',
            'totalDebt',
            'recentTransactions',
            'unsettledLoansCount',
            'cashFlowStats',
            'chartPeriod',
        );
    }

    private function buildUpcomingReminders($upcomingLoanPayments, $upcomingRecurring): Collection
    {
        $reminders = $upcomingLoanPayments->map(fn (Loan $loan) => [
            'kind' => 'loan',
            'name' => $loan->name,
            'amount' => (float) ($loan->next_period_amount ?? $loan->monthly_payment ?? 0),
            'due_date' => $loan->payment_due_date,
            'days_until' => $loan->days_until_payment,
            'type_label' => ($loan->days_until_payment ?? 0) < 0 ? 'Trả khoản vay · Quá hạn' : 'Trả khoản vay',
            'type_badge_class' => ($loan->days_until_payment ?? 0) < 0 ? 'bg-red-100 text-red-800' : 'bg-indigo-100 text-indigo-800',
            'insufficient_funds' => false,
            'wallet' => $loan->wallet,
            'loan' => $loan,
            'pay_url' => route('loans.index', array_filter([
                'pay' => $loan->id,
                'period' => $loan->next_period_id ?? null,
                'amount' => $loan->next_period_amount ?? null,
            ])),
            'recurring' => null,
        ]);

        foreach ($upcomingRecurring as $item) {
            $overdue = ($item->days_until ?? 0) < 0;
            $typeLabel = $item->type_label.($overdue ? ' · Quá hạn '.abs($item->days_until).' ngày' : '');

            $reminders->push([
                'kind' => 'recurring',
                'name' => $item->name,
                'amount' => (float) $item->amount,
                'due_date' => $item->due_date,
                'days_until' => $item->days_until,
                'type_label' => $typeLabel,
                'type_badge_class' => $overdue
                    ? 'bg-red-100 text-red-800'
                    : ($item->type === 'income' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800'),
                'insufficient_funds' => $item->insufficient_funds,
                'wallet' => $item->wallet,
                'loan' => null,
                'pay_url' => route('transactions.create', array_filter([
                    'type' => $item->type,
                    'amount' => $item->amount,
                    'description' => $item->name,
                    'transacted_at' => $item->due_date?->format('d/m/Y'),
                    'recurring_item_id' => $item->item_id,
                    'recurring_occurrence_id' => $item->occurrence_id,
                ])),
                'recurring' => $item,
            ]);
        }

        return $reminders->sortBy('due_date')->values();
    }
}
