<?php

namespace Tests\Feature\Calendar;

use App\Models\Holiday;
use Database\Seeders\HolidaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HolidaySeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function seeds_vietnam_public_holidays_from_2020_to_2026(): void
    {
        $this->seed(HolidaySeeder::class);

        $expectedCount = count(require database_path('data/vietnam_public_holidays.php'));

        $this->assertSame($expectedCount, Holiday::count());
        $this->assertSame(1, Holiday::whereDate('date', '2020-01-25')->count());
        $this->assertSame(1, Holiday::whereDate('date', '2026-02-17')->count());
        $this->assertDatabaseHas('holidays', ['name' => 'Giỗ Tổ Hùng Vương']);
    }
}
