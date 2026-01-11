<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class HolidayCalendarService
{
    /**
     * @return array<int, string>
     */
    public function getHolidayDates(int $year): array
    {
        $cacheKey = "holidays.{$year}";
        $ttl = (int) config('holidays.cache_ttl', 43200);

        return Cache::remember($cacheKey, $ttl, function () use ($year) {
            $dates = $this->fetchHolidayDates($year);
            $fallback = config('holidays.dates', []);

            if (! empty($fallback)) {
                $dates = array_merge($dates, $fallback);
            }

            return array_values(array_unique($dates));
        });
    }

    public function isHoliday(Carbon $date): bool
    {
        $dates = $this->getHolidayDates((int) $date->year);

        return in_array($date->toDateString(), $dates, true);
    }

    /**
     * @return array<int, string>
     */
    protected function fetchHolidayDates(int $year): array
    {
        $source = config('holidays.source');
        if (! $source) {
            return [];
        }

        try {
            $response = Http::timeout(5)->get($source);
        } catch (\Throwable $e) {
            return [];
        }

        if (! $response->ok()) {
            return [];
        }

        $raw = (string) $response->body();
        if ($raw === '') {
            return [];
        }

        $raw = preg_replace("/\r\n[ \t]/", '', $raw) ?? $raw;
        $lines = preg_split("/\r\n|\n|\r/", $raw) ?: [];
        $dates = [];

        foreach ($lines as $line) {
            if (! str_starts_with($line, 'DTSTART')) {
                continue;
            }

            if (! preg_match('/:(\d{8})/', $line, $matches)) {
                continue;
            }

            $dateStr = $matches[1];
            if (substr($dateStr, 0, 4) !== (string) $year) {
                continue;
            }

            try {
                $dates[] = Carbon::createFromFormat('Ymd', $dateStr)->toDateString();
            } catch (\Exception $e) {
                continue;
            }
        }

        return $dates;
    }
}
