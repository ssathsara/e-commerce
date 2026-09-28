<?php

namespace App\Services\Reports;

use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Portable SQL for grouping timestamps by day or month (MySQL/MariaDB and SQLite),
 * plus helpers to fill gaps so charts have a point for every period.
 */
final class DateBucket
{
    public static function expression(string $column, string $grain): string
    {
        $driver = DB::connection()->getDriverName();

        return match ([$driver, $grain]) {
            ['sqlite', 'month'] => "strftime('%Y-%m', {$column})",
            ['sqlite', 'day'] => "strftime('%Y-%m-%d', {$column})",
            [$driver, 'month'] => "DATE_FORMAT({$column}, '%Y-%m')",
            default => "DATE_FORMAT({$column}, '%Y-%m-%d')",
        };
    }

    /** @return list<string> */
    public static function periods(Carbon $from, Carbon $to, string $grain): array
    {
        $format = $grain === 'month' ? 'Y-m' : 'Y-m-d';
        $interval = $grain === 'month' ? '1 month' : '1 day';
        $start = $grain === 'month' ? $from->copy()->startOfMonth() : $from->copy()->startOfDay();

        return collect(CarbonPeriod::create($start, $interval, $to))
            ->map(fn ($date) => $date->format($format))
            ->unique()
            ->values()
            ->all();
    }
}
