<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{date: string, name: string, type: string}> $holidays */
        $holidays = require database_path('data/vietnam_public_holidays.php');

        foreach ($holidays as $holiday) {
            $existing = Holiday::whereDate('date', $holiday['date'])->first();

            if ($existing) {
                $existing->update([
                    'name' => $holiday['name'],
                    'type' => $holiday['type'],
                ]);
            } else {
                Holiday::create($holiday);
            }
        }
    }
}
