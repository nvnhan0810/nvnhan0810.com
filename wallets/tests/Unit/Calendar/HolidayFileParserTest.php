<?php

namespace Tests\Unit\Calendar;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Wallets\Calendar\Application\HolidayFileParser;

class HolidayFileParserTest extends TestCase
{
    #[Test]
    public function parses_csv_with_header_and_vietnamese_dates(): void
    {
        $path = $this->createTempCsv([
            ['Ngày', 'Tên ngày lễ', 'Loại'],
            ['01/01/2026', 'Tết Dương Lịch', 'public'],
            ['30/04/2026', 'Giải phóng miền Nam', 'bank'],
        ]);

        $result = (new HolidayFileParser)->parse($path, 'csv');

        $this->assertSame([], $result['errors']);
        $this->assertCount(2, $result['rows']);
        $this->assertSame('2026-01-01', $result['rows'][0]['date']);
        $this->assertSame('Tết Dương Lịch', $result['rows'][0]['name']);
        $this->assertSame('public', $result['rows'][0]['type']);
        $this->assertSame('bank', $result['rows'][1]['type']);
    }

    #[Test]
    public function reports_invalid_rows_in_csv(): void
    {
        $path = $this->createTempCsv([
            ['01/01/2026', 'Hợp lệ', 'public'],
            ['', 'Thiếu ngày', 'public'],
            ['không-hợp-lệ', 'Ngày sai', 'public'],
        ]);

        $result = (new HolidayFileParser)->parse($path, 'csv');

        $this->assertCount(1, $result['rows']);
        $this->assertCount(2, $result['errors']);
    }

    #[Test]
    public function parses_csv_with_utf8_bom(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'holiday_csv_bom_');
        file_put_contents($path, "\xEF\xBB\xBFNgày,Tên ngày lễ,Loại\n01/01/2026,Tết Dương Lịch,public\n");

        $result = (new HolidayFileParser)->parse($path, 'csv');

        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('Tết Dương Lịch', $result['rows'][0]['name']);
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function createTempCsv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'holiday_csv_');
        $handle = fopen($path, 'w');

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }
}
