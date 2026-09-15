<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Wallets\Reporting\Application\Query\GetFixedExpenseSummary;
use Wallets\Reporting\Domain\FixedExpenseGranularity;
use Wallets\Shared\Application\QueryBus;

class FixedExpenseSummaryController extends Controller
{
    public function __construct(private readonly QueryBus $queries) {}

    public function index(Request $request)
    {
        $data = $this->queries->ask(new GetFixedExpenseSummary(
            userId: (int) auth()->id(),
            granularity: (string) $request->get('granularity', FixedExpenseGranularity::MONTH),
            start: $request->get('start'),
            duration: (int) $request->get('duration', FixedExpenseGranularity::DEFAULT_DURATION),
            customDays: (int) $request->get('custom_days', FixedExpenseGranularity::DEFAULT_CUSTOM_DAYS),
        ));

        return Inertia::render('FixedExpenses/Summary', $data);
    }
}
