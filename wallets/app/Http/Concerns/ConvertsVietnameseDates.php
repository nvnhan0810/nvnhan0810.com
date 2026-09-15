<?php

namespace App\Http\Concerns;

use Carbon\Carbon;

trait ConvertsVietnameseDates
{
    protected function convertDateFormat(string $date): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
            return $matches[3].'-'.$matches[2].'-'.$matches[1];
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $date)->format('Y-m-d');
        } catch (\Exception $e) {
            return $date;
        }
    }
}
