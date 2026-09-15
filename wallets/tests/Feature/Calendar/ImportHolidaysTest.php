<?php

namespace Tests\Feature\Calendar;

use App\Models\Holiday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Wallets\Calendar\Application\Command\ImportHolidays;
use Wallets\Shared\Application\CommandBus;

class ImportHolidaysTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_can_import_holidays_from_csv(): void
    {
        $user = User::factory()->create(['email' => 'holiday-import@example.com']);

        $csv = "Ngày,Tên ngày lễ,Loại\n".
            "01/01/2026,Tết Dương Lịch,public\n".
            "30/04/2026,Giải phóng miền Nam,public\n";

        $file = UploadedFile::fake()->createWithContent('holidays.csv', $csv);

        $response = $this->actingAs($user)->post(route('holidays.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('holidays.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('holidays', [
            'name' => 'Tết Dương Lịch',
            'type' => 'public',
        ]);
        $this->assertDatabaseHas('holidays', [
            'name' => 'Giải phóng miền Nam',
        ]);
        $this->assertSame(2, Holiday::whereDate('date', '>=', '2026-01-01')->count());
    }

    #[Test]
    public function import_updates_existing_holiday_by_date(): void
    {
        Holiday::create([
            'date' => '2026-01-01',
            'name' => 'Tên cũ',
            'type' => 'custom',
        ]);

        $result = app(CommandBus::class)->dispatch(new ImportHolidays(rows: [
            ['date' => '2026-01-01', 'name' => 'Tết Dương Lịch', 'type' => 'public'],
        ]));

        $this->assertSame(0, $result->created);
        $this->assertSame(1, $result->updated);
        $this->assertDatabaseHas('holidays', [
            'name' => 'Tết Dương Lịch',
            'type' => 'public',
        ]);
        $this->assertSame(1, Holiday::whereDate('date', '2026-01-01')->count());
    }

    #[Test]
    public function import_template_csv_includes_utf8_bom_for_excel(): void
    {
        $user = User::factory()->create(['email' => 'holiday-template@example.com']);

        $response = $this->actingAs($user)->get(route('holidays.import.template'));

        $response->assertOk();
        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Ngày', $content);
        $this->assertStringContainsString('Tên ngày lễ', $content);
        $this->assertStringContainsString('Giải phóng miền Nam', $content);
    }

    #[Test]
    public function import_accepts_csv_with_utf8_bom(): void
    {
        $user = User::factory()->create(['email' => 'holiday-bom@example.com']);

        $csv = "\xEF\xBB\xBFNgày,Tên ngày lễ,Loại\n".
            "01/01/2026,Tết Dương Lịch,public\n";

        $file = UploadedFile::fake()->createWithContent('holidays.csv', $csv);

        $response = $this->actingAs($user)->post(route('holidays.import'), [
            'file' => $file,
        ]);

        $response->assertRedirect(route('holidays.index'));
        $this->assertDatabaseHas('holidays', [
            'name' => 'Tết Dương Lịch',
        ]);
    }
}
