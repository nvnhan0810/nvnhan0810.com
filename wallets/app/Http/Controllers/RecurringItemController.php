<?php

namespace App\Http\Controllers;

use App\Models\RecurringItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Wallets\RecurringPlanning\Application\Command\CreateRecurringItem;
use Wallets\RecurringPlanning\Application\Command\DeleteRecurringItem;
use Wallets\RecurringPlanning\Application\Command\UpdateRecurringItem;
use Wallets\RecurringPlanning\Application\Query\ListRecurringItems;
use Wallets\Shared\Application\CommandBus;
use Wallets\Shared\Application\QueryBus;

class RecurringItemController extends Controller
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {}

    public function index()
    {
        $items = $this->queries->ask(new ListRecurringItems(userId: auth()->id()));

        return Inertia::render('RecurringItems/Index', [
            'items' => collect($items)->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'type' => $item->type,
                    'amount' => (float) $item->amount,
                    'wallet_id' => $item->wallet_id,
                    'day_of_month' => $item->day_of_month,
                    'effective_from' => optional($item->effective_from)?->toDateString(),
                    'ends_at' => optional($item->ends_at)?->toDateString(),
                    'note' => $item->note,
                    'is_active' => (bool) $item->is_active,
                    'next_due' => isset($item->next_due) ? (string) $item->next_due : null,
                    'days_until' => $item->days_until ?? null,
                    'insufficient_funds' => false,
                ];
            })->values()->all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0.01',
            'day_of_month' => 'required|integer|min:1|max:31',
            'effective_from' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:effective_from',
            'note' => 'nullable|string',
        ]);
        $validated['wallet_id'] = null;

        $this->commands->dispatch(new CreateRecurringItem(
            userId: auth()->id(),
            data: $validated,
        ));

        return redirect()->route('recurring-items.index')->with('success', 'Đã thêm khoản thu/chi cố định.');
    }

    public function update(Request $request, RecurringItem $recurringItem)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0.01',
            'day_of_month' => 'required|integer|min:1|max:31',
            'effective_from' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:effective_from',
            'note' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['wallet_id'] = null;

        $this->commands->dispatch(new UpdateRecurringItem(
            userId: auth()->id(),
            recurringItemId: $recurringItem->id,
            data: $validated,
        ));

        return redirect()->route('recurring-items.index')->with('success', 'Đã cập nhật.');
    }

    public function destroy(RecurringItem $recurringItem)
    {
        $this->commands->dispatch(new DeleteRecurringItem(
            userId: auth()->id(),
            recurringItemId: $recurringItem->id,
        ));

        return redirect()->route('recurring-items.index')->with('success', 'Đã xóa.');
    }
}
