<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FixedExpenseSummaryController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\RecurringItemController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionTemplateController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('/auth/google', [AuthController::class, 'redirectGoogle'])->name('auth.google');
    Route::get('/auth/google/callback', [AuthController::class, 'callbackGoogle'])->name('auth.google.callback');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
    Route::get('/loans/create', [LoanController::class, 'create'])->name('loans.create');
    Route::post('/loans/preview', [LoanController::class, 'preview'])->name('loans.preview');
    Route::post('/loans', [LoanController::class, 'store'])->name('loans.store');
    Route::get('/loans/{loan}', [LoanController::class, 'show'])->name('loans.show');
    Route::post('/payments', [LoanController::class, 'storePayment'])->name('payments.store');
    Route::post('/loans/{loan}/settle', [LoanController::class, 'settle'])->name('loans.settle');

    Route::get('/wallets/sort', [WalletController::class, 'sort'])->name('wallets.sort');
    Route::post('/wallets/sort', [WalletController::class, 'updateSort'])->name('wallets.update-sort');
    Route::resource('wallets', WalletController::class)->except(['show']);

    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
    Route::get('/transactions/bulk', [TransactionController::class, 'createBulk'])->name('transactions.bulk');
    Route::post('/transactions/bulk', [TransactionController::class, 'storeBulk'])->name('transactions.bulk.store');
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

    Route::get('/recurring-items', [RecurringItemController::class, 'index'])->name('recurring-items.index');
    Route::post('/recurring-items', [RecurringItemController::class, 'store'])->name('recurring-items.store');
    Route::put('/recurring-items/{recurringItem}', [RecurringItemController::class, 'update'])->name('recurring-items.update');
    Route::delete('/recurring-items/{recurringItem}', [RecurringItemController::class, 'destroy'])->name('recurring-items.destroy');

    Route::get('/fixed-expenses', [FixedExpenseSummaryController::class, 'index'])->name('fixed-expenses.index');

    Route::get('/transaction-templates', [TransactionTemplateController::class, 'index'])->name('transaction-templates.index');
    Route::post('/transaction-templates', [TransactionTemplateController::class, 'store'])->name('transaction-templates.store');
    Route::delete('/transaction-templates/{transactionTemplate}', [TransactionTemplateController::class, 'destroy'])->name('transaction-templates.destroy');

    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');

    Route::get('/holidays', [HolidayController::class, 'index'])->name('holidays.index');
    Route::post('/holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::post('/holidays/import', [HolidayController::class, 'import'])->name('holidays.import');
    Route::get('/holidays/import/template', [HolidayController::class, 'importTemplate'])->name('holidays.import.template');
    Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');
});
