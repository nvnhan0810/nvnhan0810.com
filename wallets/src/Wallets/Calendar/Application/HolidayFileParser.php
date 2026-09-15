<?php

namespace Wallets\Calendar\Application;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

final class HolidayFileParser
{
    private const HEADER_KEYWORDS = ['ngày', 'ngay', 'date', 'tên', 'ten', 'name', 'loại', 'loai', 'type'];

    /**
     * @return array{rows: list<array{date: string, name: string, type: string}>, errors: list<string>}
     */
    public function parse(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        return match ($extension) {
            'csv', 'txt' => $this->parseCsv($path),
            'xlsx', 'xls' => $this->parseExcel($path),
            default => ['rows' => [], 'errors' => ['Định dạng file không được hỗ trợ. Vui lòng dùng CSV hoặc Excel (.xlsx, .xls).']],
        };
    }

    /**
     * @return array{rows: list<array{date: string, name: string, type: string}>, errors: list<string>}
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return ['rows' => [], 'errors' => ['Không thể đọc file CSV.']];
        }

        $rows = [];
        $errors = [];
        $lineNumber = 0;
        $skipHeader = null;

        while (($line = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if ($this->isEmptyLine($line)) {
                continue;
            }

            $line = $this->stripUtf8BomFromLine($line);

            if ($skipHeader === null) {
                $skipHeader = $this->looksLikeHeader($line);
            }

            if ($skipHeader && $lineNumber === 1) {
                continue;
            }

            $parsed = $this->parseRow($line, $lineNumber);
            if ($parsed['row'] !== null) {
                $rows[] = $parsed['row'];
            }
            if ($parsed['error'] !== null) {
                $errors[] = $parsed['error'];
            }
        }

        fclose($handle);

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @return array{rows: list<array{date: string, name: string, type: string}>, errors: list<string>}
     */
    private function parseExcel(string $path): array
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable) {
            return ['rows' => [], 'errors' => ['Không thể đọc file Excel.']];
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = [];
        $errors = [];
        $lineNumber = 0;
        $skipHeader = null;

        foreach ($sheet->getRowIterator() as $row) {
            $lineNumber++;
            $cells = [];

            foreach ($row->getCellIterator() as $cell) {
                $cells[] = $cell->getCalculatedValue();
            }

            if ($this->isEmptyLine($cells)) {
                continue;
            }

            if ($skipHeader === null) {
                $skipHeader = $this->looksLikeHeader($cells);
            }

            if ($skipHeader && $lineNumber === 1) {
                continue;
            }

            $parsed = $this->parseRow($cells, $lineNumber);
            if ($parsed['row'] !== null) {
                $rows[] = $parsed['row'];
            }
            if ($parsed['error'] !== null) {
                $errors[] = $parsed['error'];
            }
        }

        return ['rows' => $rows, 'errors' => $errors];
    }

    /**
     * @param  array<int, mixed>  $line
     * @return array{row: ?array{date: string, name: string, type: string}, error: ?string}
     */
    private function parseRow(array $line, int $lineNumber): array
    {
        $dateRaw = trim((string) ($line[0] ?? ''));
        $name = trim((string) ($line[1] ?? ''));
        $typeRaw = trim((string) ($line[2] ?? ''));

        if ($dateRaw === '' && $name === '') {
            return ['row' => null, 'error' => null];
        }

        if ($dateRaw === '' || $name === '') {
            return ['row' => null, 'error' => "Dòng {$lineNumber}: thiếu ngày hoặc tên ngày lễ."];
        }

        $date = $this->parseDate($dateRaw);
        if ($date === null) {
            return ['row' => null, 'error' => "Dòng {$lineNumber}: ngày không hợp lệ ({$dateRaw}). Dùng dd/mm/yyyy hoặc yyyy-mm-dd."];
        }

        $type = $this->parseType($typeRaw);

        return [
            'row' => [
                'date' => $date,
                'name' => $name,
                'type' => $type,
            ],
            'error' => null,
        ];
    }

    private function parseDate(mixed $value): ?string
    {
        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        $value = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value)->format('Y-m-d');
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches)) {
            return $matches[3].'-'.$matches[2].'-'.$matches[1];
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseType(string $value): string
    {
        if ($value === '') {
            return 'public';
        }

        $normalized = mb_strtolower($value);

        return match (true) {
            in_array($normalized, ['bank', 'ngân hàng', 'ngan hang', 'ngày nghỉ ngân hàng'], true) => 'bank',
            in_array($normalized, ['custom', 'tùy chỉnh', 'tuy chinh'], true) => 'custom',
            default => 'public',
        };
    }

    /**
     * @param  array<int, mixed>  $line
     */
    private function looksLikeHeader(array $line): bool
    {
        foreach ($line as $cell) {
            $cell = mb_strtolower(trim((string) $cell));
            foreach (self::HEADER_KEYWORDS as $keyword) {
                if (str_contains($cell, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $line
     */
    private function isEmptyLine(array $line): bool
    {
        foreach ($line as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $line
     * @return array<int, mixed>
     */
    private function stripUtf8BomFromLine(array $line): array
    {
        if ($line === []) {
            return $line;
        }

        $first = (string) ($line[0] ?? '');
        if (str_starts_with($first, "\xEF\xBB\xBF")) {
            $line[0] = substr($first, 3);
        }

        return $line;
    }
}
