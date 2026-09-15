<?php

namespace App\Http\Controllers;

use App\Support\InertiaData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Wallets\Lending\Application\Query\ListActiveLoans;
use Wallets\Reporting\Application\Query\GetDashboardOverview;
use Wallets\Shared\Application\QueryBus;

class DashboardController extends Controller
{
    public function __construct(private readonly QueryBus $queries) {}

    public function index(Request $request)
    {
        $userId = auth()->id();

        $data = $this->queries->ask(new GetDashboardOverview(
            userId: $userId,
            chartPeriod: $request->get('period', 'month'),
        ));

        $activeLoansData = $this->queries->ask(new ListActiveLoans($userId));
        $data['totalDebt'] += $activeLoansData['totalRemaining'] ?? 0;

        $data['wallets'] = InertiaData::wallets(collect($data['wallets'] ?? []));
        $data['recentTransactions'] = collect($data['recentTransactions'] ?? [])
            ->map(fn ($tx) => InertiaData::transaction($tx))
            ->values()
            ->all();

        $data['upcomingReminders'] = collect($data['upcomingReminders'] ?? [])
            ->map(fn (array $r) => InertiaData::reminder($r))
            ->values()
            ->all();

        return Inertia::render('Dashboard/Index', $data);
    }
}
