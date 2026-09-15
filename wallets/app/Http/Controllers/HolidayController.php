<?php

namespace App\Http\Controllers;

use App\Http\Concerns\ConvertsVietnameseDates;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Wallets\Calendar\Application\Command\CreateHoliday;
use Wallets\Calendar\Application\Command\DeleteHoliday;
use Wallets\Calendar\Application\Command\ImportHolidays;
use Wallets\Calendar\Application\HolidayFileParser;
use Wallets\Calendar\Application\ImportHolidaysResult;
use Wallets\Calendar\Application\Query\ListHolidays;
use Wallets\Shared\Application\CommandBus;
use Wallets\Shared\Application\QueryBus;

class HolidayController extends Controller
{
    use ConvertsVietnameseDates;

    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {}

    public function index()
    {
        $holidays = $this->queries->ask(new ListHolidays);

        return Inertia::render('Holidays/Index', [
            'holidays' => [
                'data' => $holidays->getCollection()->map(fn ($h) => [
                    'id' => $h->id,
                    'name' => $h->name,
                    'date' => optional($h->date)?->format('d/m/Y'),
                    'type' => $h->type,
                ])->values()->all(),
                'links' => $holidays->linkCollection()->toArray(),
                'meta' => [
                    'current_page' => $holidays->currentPage(),
                    'last_page' => $holidays->lastPage(),
                    'total' => $holidays->total(),
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|string',
            'name' => 'required|string|max:255',
            'type' => 'required|in:public,bank,custom',
        ]);

        $validated['date'] = $this->convertDateFormat($validated['date']);

        $this->commands->dispatch(new CreateHoliday(data: $validated));

        return redirect()->route('holidays.index')->with('success', 'Đã thêm ngày lễ');
    }

    public function destroy(Holiday $holiday)
    {
        $this->commands->dispatch(new DeleteHoliday(holidayId: $holiday->id));

        return redirect()->route('holidays.index')->with('success', 'Đã xóa ngày lễ');
    }

    public function import(Request $request, HolidayFileParser $parser)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:2048',
        ]);

        $file = $validated['file'];
        $parsed = $parser->parse($file->getRealPath(), $file->getClientOriginalExtension());

        if ($parsed['rows'] === [] && $parsed['errors'] !== []) {
            return redirect()
                ->route('holidays.index')
                ->withErrors(['file' => $parsed['errors'][0]])
                ->with('import_errors', $parsed['errors']);
        }

        if ($parsed['rows'] === []) {
            return redirect()
                ->route('holidays.index')
                ->withErrors(['file' => 'File không có dữ liệu ngày lễ hợp lệ.']);
        }

        /** @var ImportHolidaysResult $result */
        $result = $this->commands->dispatch(new ImportHolidays(rows: $parsed['rows']));

        $allErrors = array_merge($parsed['errors'], $result->errors);
        $message = "Import hoàn tất: {$result->created} mới, {$result->updated} cập nhật";

        if ($result->skipped > 0) {
            $message .= ", {$result->skipped} bỏ qua";
        }

        return redirect()
            ->route('holidays.index')
            ->with('success', $message.'.')
            ->with('import_errors', $allErrors);
    }

    public function importTemplate(): StreamedResponse
    {
        /** @var list<array{date: string, name: string, type: string}> $holidays */
        $holidays = require database_path('data/vietnam_public_holidays.php');

        return response()->streamDownload(function () use ($holidays) {
            $handle = fopen('php://output', 'w');
            // Excel on Windows needs UTF-8 BOM to display Vietnamese correctly.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Ngày', 'Tên ngày lễ', 'Loại']);

            foreach ($holidays as $holiday) {
                fputcsv($handle, [
                    Carbon::parse($holiday['date'])->format('d/m/Y'),
                    $holiday['name'],
                    $holiday['type'],
                ]);
            }

            fclose($handle);
        }, 'ngay-le-vietnam-2020-2026.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
