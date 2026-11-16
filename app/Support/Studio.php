<?php

namespace App\Support;

use App\Models\Order;
use App\Models\StudioSlot;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Logika jadwal studio: slot harian, durasi paket, dan ketersediaan.
 * Semua waktu memakai zona waktu aplikasi (WIB / Asia/Jakarta).
 */
class Studio
{
    public static function now(): Carbon
    {
        return Carbon::now(config('app.timezone'));
    }

    public static function today(): Carbon
    {
        return self::now()->startOfDay();
    }

    public static function minDate(): Carbon
    {
        return self::today()->addDays((int) config('studio.min_lead_days', 1));
    }

    public static function maxDate(): Carbon
    {
        return self::today()->addMonthsNoOverflow((int) config('studio.max_months_ahead', 4))->endOfMonth()->startOfDay();
    }

    public static function slots(): array
    {
        return config('studio.slots', ['09.00', '13.00', '15.30']);
    }

    public static function toMinutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode('.', str_replace(':', '.', $time)) + [0, 0]);
        return $h * 60 + $m;
    }

    public static function fromMinutes(int $minutes): string
    {
        $minutes = $minutes % (24 * 60);
        return sprintf('%02d.%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public static function endTime(string $start, int $duration): string
    {
        return self::fromMinutes(self::toMinutes($start) + $duration);
    }

    /** Contoh: "15.30–17.30 WIB · 120 menit" */
    public static function slotLabel(string $start, int $duration): string
    {
        return $start . '–' . self::endTime($start, $duration) . ' WIB · ' . $duration . ' menit';
    }

    /** Ambil [mulai, selesai] (menit) dari label jadwal yang tersimpan di pesanan. */
    public static function parseRange(?string $label): ?array
    {
        if ($label && preg_match('/(\d{1,2})[.:](\d{2})\s*[–-]\s*(\d{1,2})[.:](\d{2})/u', $label, $m)) {
            return [(int) $m[1] * 60 + (int) $m[2], (int) $m[3] * 60 + (int) $m[4]];
        }
        if ($label && preg_match('/(\d{1,2})[.:](\d{2})/', $label, $m)) {
            $start = (int) $m[1] * 60 + (int) $m[2];
            return [$start, $start + 90];
        }
        return null;
    }

    public static function dateLabel($date, string $format = 'l, j F Y'): string
    {
        $c = $date instanceof CarbonInterface ? $date->copy() : Carbon::parse($date, config('app.timezone'));
        return $c->locale('id')->translatedFormat($format);
    }

    /**
     * Status tiap slot untuk rentang tanggal.
     * Hasil: ['2026-11-20' => ['09.00' => 'available'|'full'|'blocked', ...], ...]
     */
    public static function availability(Carbon $from, Carbon $to, int $duration, ?int $ignoreOrderId = null): array
    {
        $fromStr = $from->toDateString();
        $toStr = $to->toDateString();

        $orders = Order::query()
            ->whereIn('session_status', config('studio.occupying_statuses'))
            ->whereDate('session_date', '>=', $fromStr)
            ->whereDate('session_date', '<=', $toStr)
            ->when($ignoreOrderId, fn ($q) => $q->where('id', '!=', $ignoreOrderId))
            ->get(['id', 'session_date', 'session_time_slot']);

        $blocks = StudioSlot::query()
            ->where('type', 'blocked')
            ->whereDate('date', '>=', $fromStr)
            ->whereDate('date', '<=', $toStr)
            ->get(['date', 'start_time', 'end_time']);

        $busy = [];  // date => [[start,end], ...]
        foreach ($orders as $o) {
            $range = self::parseRange($o->session_time_slot);
            if ($range) {
                $busy[$o->session_date->toDateString()][] = $range;
            }
        }

        $blocked = [];
        foreach ($blocks as $b) {
            $blocked[$b->date->toDateString()][] = [
                self::toMinutes(substr((string) $b->start_time, 0, 5)),
                self::toMinutes(substr((string) $b->end_time, 0, 5)),
            ];
        }

        $result = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            foreach (self::slots() as $slot) {
                $start = self::toMinutes($slot);
                $end = $start + $duration;
                $status = 'available';
                foreach ($blocked[$key] ?? [] as [$bs, $be]) {
                    if ($start < $be && $end > $bs) {
                        $status = 'blocked';
                        break;
                    }
                }
                if ($status === 'available') {
                    foreach ($busy[$key] ?? [] as [$os, $oe]) {
                        if ($start < $oe && $end > $os) {
                            $status = 'full';
                            break;
                        }
                    }
                }
                $result[$key][$slot] = $status;
            }
        }

        return $result;
    }

    /** Apakah slot (tanggal + jam mulai) masih bisa dipesan untuk durasi tertentu? */
    public static function isAvailable(string $date, string $start, int $duration, ?int $ignoreOrderId = null): bool
    {
        $day = Carbon::parse($date, config('app.timezone'))->startOfDay();
        if ($day->lt(self::minDate()) || $day->gt(self::maxDate()) || !in_array($start, self::slots(), true)) {
            return false;
        }

        $map = self::availability($day, $day, $duration, $ignoreOrderId);
        return ($map[$day->toDateString()][$start] ?? null) === 'available';
    }
}
