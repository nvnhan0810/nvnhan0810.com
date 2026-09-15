<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Wallets\Calendar\Application\Command\CreateHoliday;
use Wallets\Calendar\Application\Command\DeleteHoliday;
use Wallets\Calendar\Application\Command\ImportHolidays;
use Wallets\Calendar\Application\Handler\CreateHolidayHandler;
use Wallets\Calendar\Application\Handler\DeleteHolidayHandler;
use Wallets\Calendar\Application\Handler\ImportHolidaysHandler;
use Wallets\Calendar\Application\Handler\ListHolidaysHandler;
use Wallets\Calendar\Application\Query\ListHolidays;
use Wallets\Catalog\Application\Command\CreateTransactionTemplate;
use Wallets\Catalog\Application\Command\DeleteTransactionTemplate;
use Wallets\Catalog\Application\Handler\CreateTransactionTemplateHandler;
use Wallets\Catalog\Application\Handler\DeleteTransactionTemplateHandler;
use Wallets\Catalog\Application\Handler\ListTemplatesHandler;
use Wallets\Catalog\Application\Query\ListTemplates;
use Wallets\Identity\Application\Handler\IsEmailAllowedHandler;
use Wallets\Identity\Application\Query\IsEmailAllowed;
use Wallets\Lending\Application\Command\CreateLoan;
use Wallets\Lending\Application\Command\RecordLoanPayment;
use Wallets\Lending\Application\Command\SettleLoan;
use Wallets\Lending\Application\Handler\CreateLoanHandler;
use Wallets\Lending\Application\Handler\GetLoanDetailHandler;
use Wallets\Lending\Application\Handler\ListActiveLoansHandler;
use Wallets\Lending\Application\Handler\RecordLoanPaymentHandler;
use Wallets\Lending\Application\Handler\SettleLoanHandler;
use Wallets\Lending\Application\Query\GetLoanDetail;
use Wallets\Lending\Application\Query\ListActiveLoans;
use Wallets\Preferences\Application\Command\UpdateSettings;
use Wallets\Preferences\Application\Handler\GetSettingsHandler;
use Wallets\Preferences\Application\Handler\UpdateSettingsHandler;
use Wallets\Preferences\Application\Query\GetSettings;
use Wallets\RecurringPlanning\Application\Command\CreateRecurringItem;
use Wallets\RecurringPlanning\Application\Command\DeleteRecurringItem;
use Wallets\RecurringPlanning\Application\Command\UpdateRecurringItem;
use Wallets\RecurringPlanning\Application\Handler\CreateRecurringItemHandler;
use Wallets\RecurringPlanning\Application\Handler\DeleteRecurringItemHandler;
use Wallets\RecurringPlanning\Application\Handler\ListRecurringItemsHandler;
use Wallets\RecurringPlanning\Application\Handler\UpdateRecurringItemHandler;
use Wallets\RecurringPlanning\Application\Query\ListRecurringItems;
use Wallets\Reporting\Application\Handler\GetDashboardOverviewHandler;
use Wallets\Reporting\Application\Handler\GetFixedExpenseSummaryHandler;
use Wallets\Reporting\Application\Query\GetDashboardOverview;
use Wallets\Reporting\Application\Query\GetFixedExpenseSummary;
use Wallets\Shared\Application\Clock;
use Wallets\Shared\Application\CommandBus;
use Wallets\Shared\Application\Config;
use Wallets\Shared\Application\Logger;
use Wallets\Shared\Application\QueryBus;
use Wallets\Shared\Infrastructure\LaravelClock;
use Wallets\Shared\Infrastructure\LaravelConfig;
use Wallets\Shared\Infrastructure\LaravelLogger;
use Wallets\WalletAccounting\Application\Command\CreateWallet;
use Wallets\WalletAccounting\Application\Command\DeleteTransaction;
use Wallets\WalletAccounting\Application\Command\DeleteWallet;
use Wallets\WalletAccounting\Application\Command\RecordAdjustment;
use Wallets\WalletAccounting\Application\Command\RecordIncomeExpense;
use Wallets\WalletAccounting\Application\Command\TransferBetweenWallets;
use Wallets\WalletAccounting\Application\Command\UpdateWallet;
use Wallets\WalletAccounting\Application\Handler\CreateWalletHandler;
use Wallets\WalletAccounting\Application\Handler\DeleteTransactionHandler;
use Wallets\WalletAccounting\Application\Handler\DeleteWalletHandler;
use Wallets\WalletAccounting\Application\Handler\ListTransactionsHandler;
use Wallets\WalletAccounting\Application\Handler\ListWalletsHandler;
use Wallets\WalletAccounting\Application\Handler\RecordAdjustmentHandler;
use Wallets\WalletAccounting\Application\Handler\RecordIncomeExpenseHandler;
use Wallets\WalletAccounting\Application\Handler\TransferBetweenWalletsHandler;
use Wallets\WalletAccounting\Application\Handler\UpdateWalletHandler;
use Wallets\WalletAccounting\Application\Query\ListTransactions;
use Wallets\WalletAccounting\Application\Query\ListWallets;

class WalletsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Clock::class, LaravelClock::class);
        $this->app->bind(Config::class, LaravelConfig::class);
        $this->app->bind(Logger::class, LaravelLogger::class);

        $this->app->singleton(CommandBus::class, function ($app) {
            return new CommandBus($this->commandMap(), $app);
        });

        $this->app->singleton(QueryBus::class, function ($app) {
            return new QueryBus($this->queryMap(), $app);
        });
    }

    /** @return array<class-string, class-string> */
    private function commandMap(): array
    {
        return [
            CreateWallet::class => CreateWalletHandler::class,
            UpdateWallet::class => UpdateWalletHandler::class,
            DeleteWallet::class => DeleteWalletHandler::class,
            RecordIncomeExpense::class => RecordIncomeExpenseHandler::class,
            RecordAdjustment::class => RecordAdjustmentHandler::class,
            TransferBetweenWallets::class => TransferBetweenWalletsHandler::class,
            DeleteTransaction::class => DeleteTransactionHandler::class,
            CreateLoan::class => CreateLoanHandler::class,
            RecordLoanPayment::class => RecordLoanPaymentHandler::class,
            SettleLoan::class => SettleLoanHandler::class,
            CreateRecurringItem::class => CreateRecurringItemHandler::class,
            UpdateRecurringItem::class => UpdateRecurringItemHandler::class,
            DeleteRecurringItem::class => DeleteRecurringItemHandler::class,
            CreateTransactionTemplate::class => CreateTransactionTemplateHandler::class,
            DeleteTransactionTemplate::class => DeleteTransactionTemplateHandler::class,
            CreateHoliday::class => CreateHolidayHandler::class,
            DeleteHoliday::class => DeleteHolidayHandler::class,
            ImportHolidays::class => ImportHolidaysHandler::class,
            UpdateSettings::class => UpdateSettingsHandler::class,
        ];
    }

    /** @return array<class-string, class-string> */
    private function queryMap(): array
    {
        return [
            IsEmailAllowed::class => IsEmailAllowedHandler::class,
            ListWallets::class => ListWalletsHandler::class,
            ListTransactions::class => ListTransactionsHandler::class,
            ListActiveLoans::class => ListActiveLoansHandler::class,
            GetLoanDetail::class => GetLoanDetailHandler::class,
            ListRecurringItems::class => ListRecurringItemsHandler::class,
            ListTemplates::class => ListTemplatesHandler::class,
            ListHolidays::class => ListHolidaysHandler::class,
            GetSettings::class => GetSettingsHandler::class,
            GetDashboardOverview::class => GetDashboardOverviewHandler::class,
            GetFixedExpenseSummary::class => GetFixedExpenseSummaryHandler::class,
        ];
    }
}
