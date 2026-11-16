<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Studio;
use Illuminate\Support\Facades\Auth;

class AdminDashboardController extends Controller
{
    /** Rp1.250.000 -> "Rp1,3 jt" (ringkas untuk kartu metrik). */
    private function short(int $n): string
    {
        if ($n >= 1_000_000) {
            $v = $n / 1_000_000;
            return 'Rp' . rtrim(rtrim(number_format($v, $v >= 100 ? 0 : 1, ',', '.'), '0'), ',') . ' jt';
        }
        return 'Rp' . number_format($n, 0, ',', '.');
    }

    public function index()
    {
        $now = Studio::now();
        $today = Studio::today();
        $monthLabel = Studio::dateLabel($today, 'F');

        $greeting = match (true) {
            $now->hour < 11 => 'Selamat pagi',
            $now->hour < 15 => 'Selamat siang',
            $now->hour < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };
        $name = trim(str_ireplace('admin', '', (string) optional(Auth::user())->name)) ?: 'Admin';

        // Pesanan yang dibuat bulan ini
        $monthOrders = Order::whereYear('created_at', $today->year)->whereMonth('created_at', $today->month);
        $monthTotal = (clone $monthOrders)->count();
        $byStatus = (clone $monthOrders)->selectRaw('session_status, count(*) as c')->groupBy('session_status')->pluck('c', 'session_status');

        $valueRows = (clone $monthOrders)->where('session_status', '!=', 'Dibatalkan')->get(['total_price', 'payment_status']);
        $valueAll = (int) $valueRows->sum('total_price');
        $valueVerified = (int) $valueRows->where('payment_status', 'Terverifikasi')->sum('total_price');

        $upcoming = Order::where('session_status', 'Dikonfirmasi')
            ->whereDate('session_date', '>=', $today->toDateString())
            ->whereDate('session_date', '<=', $today->copy()->addDays(30)->toDateString())->count();

        $galleryOpen = Gallery::where('status', '!=', 'Diterbitkan');
        $galleryCounts = Gallery::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        $metrics = [
            'orders' => ['label' => 'Pesanan ' . $monthLabel, 'value' => $monthTotal,
                'note' => ($byStatus['Permintaan'] ?? 0) . ' permintaan · ' . ($byStatus['Dikonfirmasi'] ?? 0) . ' dikonfirmasi · ' . ($byStatus['Selesai'] ?? 0) . ' selesai'],
            'value' => ['value' => $this->short($valueAll),
                'note' => $this->short($valueVerified) . ' terverifikasi · ' . $this->short($valueAll - $valueVerified) . ' belum'],
            'upcoming' => ['value' => $upcoming, 'note' => '30 hari ke depan · Jadwal dikonfirmasi'],
            'gallery' => ['value' => $galleryOpen->count(),
                'note' => ($galleryCounts['Penyuntingan'] ?? 0) . ' penyuntingan · ' . ($galleryCounts['Draf'] ?? 0) . ' draf · ' . ($galleryCounts['Siap'] ?? 0) . ' siap'],
        ];

        $pendingPayments = Payment::where('status', 'Bukti terkirim')->count();

        // Tren 4 bulan terakhir (jumlah pesanan dibuat)
        $trend = [];
        for ($i = 3; $i >= 0; $i--) {
            $m = $today->copy()->startOfMonth()->subMonthsNoOverflow($i);
            $trend[] = [
                'label' => Studio::dateLabel($m, 'M'),
                'count' => Order::whereYear('created_at', $m->year)->whereMonth('created_at', $m->month)->count(),
                'current' => $i === 0,
            ];
        }
        $max = max(1, max(array_column($trend, 'count')));
        foreach ($trend as &$t) {
            $t['height'] = max(8, (int) round($t['count'] / $max * 120));
        }
        unset($t);
        $trendRange = $trend[0]['label'] . '–' . $trend[3]['label'] . ' ' . $today->year;

        // Prioritas: bukti menunggu tinjauan -> sesi siap dikonfirmasi -> sesi hari ini/besok
        $priorities = [];
        foreach (Payment::with('order')->where('status', 'Bukti terkirim')->orderBy('created_at')->limit(2)->get() as $p) {
            $o = $p->order;
            $priorities[] = [
                'title' => 'Tinjau bukti ' . $p->sender_name,
                'meta' => ($o ? $o->package_name . ' · ' : '') . Studio::dateLabel($p->created_at, 'j M') . ' · Rp' . number_format($p->nominal, 0, ',', '.'),
                'pill' => 'Menunggu verifikasi', 'pill_class' => 'status-pill-gold',
                'text' => 'Cocokkan bukti dengan invoice ' . ($o ? $o->invoice_number : '') . ' sebelum menyetujui sesi.',
                'url' => route('admin.pembayaran.detail', ['id' => $p->payment_code]), 'cta' => 'Tinjau pembayaran',
            ];
        }
        foreach (Order::where('session_status', 'Permintaan')->where('payment_status', 'Terverifikasi')->orderBy('session_date')->limit(2)->get() as $o) {
            $priorities[] = [
                'title' => 'Konfirmasi sesi ' . $o->customer_name,
                'meta' => Studio::dateLabel($o->session_date, 'j M') . ' · ' . $o->package_name . ' · ' . explode(' ', (string) $o->session_time_slot)[0],
                'pill' => 'Siap dikonfirmasi', 'pill_class' => 'status-pill-green',
                'text' => 'Pembayaran sudah terverifikasi. Jadwal belum dikonfirmasi.',
                'url' => route('admin.pesanan.detail', ['id' => $o->order_code]), 'cta' => 'Buka pesanan',
            ];
        }
        foreach (Order::where('session_status', 'Dikonfirmasi')->whereDate('session_date', '>=', $today->toDateString())->whereDate('session_date', '<=', $today->copy()->addDay()->toDateString())->orderBy('session_date')->limit(2)->get() as $o) {
            $priorities[] = [
                'title' => 'Sesi ' . ($o->session_date->isSameDay($today) ? 'hari ini' : 'besok') . ': ' . $o->customer_name,
                'meta' => Studio::dateLabel($o->session_date, 'j M') . ' · ' . $o->package_name . ' · ' . $o->session_time_slot,
                'pill' => 'Dikonfirmasi', 'pill_class' => 'status-pill-green',
                'text' => 'Lokasi: ' . $o->location,
                'url' => route('admin.pesanan.detail', ['id' => $o->order_code]), 'cta' => 'Lihat detail',
            ];
        }
        $priorities = array_slice($priorities, 0, 3);

        // Sesi terdekat
        $nearest = Order::whereIn('session_status', ['Permintaan', 'Dikonfirmasi'])
            ->whereDate('session_date', '>=', $today->toDateString())
            ->orderBy('session_date')->orderBy('session_time_slot')->limit(5)->get()
            ->map(fn (Order $o) => [
                'who' => $o->customer_name . ' · ' . $o->package_name,
                'when' => Studio::dateLabel($o->session_date, 'j M') . ' · ' . (explode(' ', (string) $o->session_time_slot)[0] ?? ''),
                'status' => $o->session_status,
                'class' => $o->session_status === 'Dikonfirmasi' ? 'status-pill-green' : 'status-pill-gold',
                'url' => route('admin.pesanan.detail', ['id' => $o->order_code]),
            ])->all();

        // Aktivitas terbaru dari data nyata
        $events = collect();
        foreach (Order::latest('id')->limit(8)->get() as $o) {
            $events->push(['at' => $o->created_at, 'title' => 'Pesanan baru masuk', 'meta' => $o->customer_name . ' · ' . $o->order_code]);
            foreach ([['confirmed_at', 'Sesi dikonfirmasi'], ['completed_at', 'Sesi ditandai selesai'], ['cancelled_at', 'Pesanan dibatalkan'], ['archived_at', 'Pesanan diarsipkan']] as [$col, $label]) {
                if ($o->$col) {
                    $events->push(['at' => $o->$col, 'title' => $label, 'meta' => $o->customer_name . ' · ' . $o->order_code]);
                }
            }
        }
        foreach (Payment::with('order')->latest('id')->limit(8)->get() as $p) {
            $events->push(['at' => $p->created_at, 'title' => 'Bukti pembayaran diterima', 'meta' => $p->sender_name . ' · ' . ($p->order ? $p->order->order_code : $p->payment_code)]);
            if ($p->verified_at) {
                $events->push(['at' => $p->verified_at, 'title' => 'Pembayaran diverifikasi', 'meta' => $p->sender_name . ' · ' . $p->payment_code]);
            }
        }
        foreach (Gallery::latest('id')->limit(4)->get() as $g) {
            $events->push(['at' => $g->created_at, 'title' => 'Galeri ' . ($g->status === 'Diterbitkan' ? 'diterbitkan' : 'disimpan sebagai ' . strtolower($g->status)), 'meta' => $g->title]);
        }
        $activity = $events->sortByDesc('at')->take(6)->map(fn ($e) => [
            'time' => $e['at']->isSameDay($today) ? $e['at']->format('H.i') : Studio::dateLabel($e['at'], 'j M'),
            'title' => $e['title'],
            'meta' => $e['meta'],
        ])->values()->all();

        return view('admin.dashboard', [
            'greeting' => $greeting, 'name' => $name,
            'dateLine' => Studio::dateLabel($today, 'l, j F Y'),
            'pendingPayments' => $pendingPayments,
            'metrics' => $metrics, 'trend' => $trend, 'trendRange' => $trendRange,
            'priorities' => $priorities, 'nearest' => $nearest, 'activity' => $activity,
        ]);
    }
}
