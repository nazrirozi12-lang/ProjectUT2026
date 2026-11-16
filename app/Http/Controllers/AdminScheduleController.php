<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\StudioSlot;
use App\Support\Studio;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminScheduleController extends Controller
{
    private function tz(): string
    {
        return config('app.timezone');
    }

    /**
     * Kalender studio: sesi nyata per tanggal, blokir studio, tanggal dapat dipilih.
     */
    public function index(Request $request)
    {
        $today = Studio::today();

        $monthParam = $request->query('bulan');
        $month = $monthParam && preg_match('/^\d{4}-\d{2}$/', $monthParam)
            ? Carbon::createFromFormat('Y-m-d', $monthParam . '-01', $this->tz())->startOfDay()
            : $today->copy()->startOfMonth();

        $dateParam = $request->query('tanggal');
        $selected = $dateParam && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateParam)
            ? Carbon::parse($dateParam, $this->tz())->startOfDay()
            : null;
        if ($selected && !$monthParam) {
            $month = $selected->copy()->startOfMonth();
        }
        // Tanpa pilihan: tandai hari ini bila bulan yang tampil adalah bulan ini
        if (!$selected && $month->isSameMonth($today)) {
            $selected = $today->copy();
        }

        $type = $request->query('jenis', 'semua');
        $search = trim((string) $request->query('q', ''));

        $gridStart = $month->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY)->startOfDay();

        $orders = Order::query()
            ->whereIn('session_status', ['Permintaan', 'Dikonfirmasi', 'Selesai'])
            ->whereDate('session_date', '>=', $gridStart->toDateString())
            ->whereDate('session_date', '<=', $gridEnd->toDateString())
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
                $q->where(fn ($w) => $w->whereLikeCi('customer_name', $like)->orWhereLikeCi('order_code', $like)->orWhereLikeCi('package_name', $like));
            })
            ->orderBy('session_time_slot')->get();

        $blocks = StudioSlot::query()->where('type', 'blocked')
            ->whereDate('date', '>=', $gridStart->toDateString())
            ->whereDate('date', '<=', $gridEnd->toDateString())
            ->orderBy('start_time')->get();

        // Himpun acara per tanggal
        $events = [];
        foreach ($orders as $o) {
            $kind = $o->session_status === 'Permintaan' ? 'tentative' : 'approved';
            if ($type !== 'semua' && $type !== $kind) {
                continue;
            }
            $start = explode(' ', (string) $o->session_time_slot)[0] ?? '';
            $events[$o->session_date->toDateString()][] = [
                'kind' => $kind,
                'text' => $start . ' ' . strtok($o->customer_name, ' '),
                'sub' => $o->session_status === 'Permintaan' ? 'Tentatif' : ($o->session_status === 'Selesai' ? 'Selesai' : 'Disetujui'),
            ];
        }
        if ($search === '') {
            foreach ($blocks as $b) {
                if ($type !== 'semua' && $type !== 'blocked') {
                    continue;
                }
                $events[$b->date->toDateString()][] = [
                    'kind' => 'blocked',
                    'text' => $this->blockLabel($b),
                    'sub' => 'Tidak tersedia',
                ];
            }
        }

        $weeks = [];
        for ($d = $gridStart->copy(); $d->lte($gridEnd); $d->addDay()) {
            $key = $d->toDateString();
            $weeks[] = [
                'date' => $key,
                'day' => $d->day,
                'muted' => !$d->isSameMonth($month),
                'today' => $d->isSameDay($today),
                'selected' => $selected && $d->isSameDay($selected),
                'events' => $events[$key] ?? [],
            ];
        }

        // Detail tanggal terpilih
        $detail = null;
        if ($selected) {
            $day = $selected->toDateString();
            $dayOrders = Order::with('package')->whereIn('session_status', ['Permintaan', 'Dikonfirmasi', 'Selesai'])
                ->whereDate('session_date', $day)->orderBy('session_time_slot')->get();
            $dayBlocks = StudioSlot::where('type', 'blocked')->whereDate('date', $day)->orderBy('start_time')->get();
            $map = Studio::availability($selected, $selected, 60);

            $detail = [
                'date' => $day,
                'label' => Studio::dateLabel($selected, 'l, j F Y'),
                'is_past' => $selected->lt($today),
                'orders' => $dayOrders->map(fn (Order $o) => [
                    'code' => $o->order_code, 'customer' => $o->customer_name, 'package' => $o->package_name,
                    'time' => $o->session_time_slot, 'location' => $o->location, 'status' => $o->session_status,
                    'payment' => $o->payment_status,
                ])->all(),
                'blocks' => $dayBlocks->map(fn (StudioSlot $b) => [
                    'id' => $b->id, 'label' => $this->blockLabel($b),
                    'start' => substr($b->start_time, 0, 5), 'end' => substr($b->end_time, 0, 5),
                    'whole_day' => substr($b->start_time, 0, 5) === '00:00' && substr($b->end_time, 0, 5) >= '23:59',
                    'note' => $b->note,
                ])->all(),
                'slots' => collect(Studio::slots())->map(fn ($s) => [
                    'time' => $s,
                    'status' => ($map[$day][$s] ?? 'available'),
                ])->all(),
            ];
        }

        // Pilihan bulan (3 bulan ke belakang sampai 6 bulan ke depan)
        $monthOptions = [];
        for ($i = -3; $i <= 6; $i++) {
            $m = $today->copy()->startOfMonth()->addMonthsNoOverflow($i);
            $monthOptions[] = ['value' => $m->format('Y-m'), 'label' => Studio::dateLabel($m, 'F Y')];
        }

        return view('admin.jadwal', [
            'weeks' => $weeks,
            'monthTitle' => Studio::dateLabel($month, 'F Y'),
            'monthValue' => $month->format('Y-m'),
            'prevMonth' => $month->copy()->subMonthNoOverflow()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonthNoOverflow()->format('Y-m'),
            'todayMonth' => $today->format('Y-m'),
            'detail' => $detail,
            'type' => $type,
            'search' => $search,
            'monthOptions' => $monthOptions,
            'defaultBlockDate' => $selected ? $selected->toDateString() : $today->toDateString(),
        ]);
    }

    private function blockLabel(StudioSlot $b): string
    {
        $s = substr($b->start_time, 0, 5);
        $e = substr($b->end_time, 0, 5);
        return ($s === '00:00' && $e >= '23:59') ? 'Studio tutup' : str_replace(':', '.', $s) . '–' . str_replace(':', '.', $e);
    }

    /** Blokir studio: seluruh hari atau rentang jam. */
    public function storeBlock(Request $request)
    {
        $data = $request->validate([
            'tanggal' => 'required|date_format:Y-m-d|after_or_equal:today',
            'cakupan' => 'required|in:hari,jam',
            'mulai' => 'required_if:cakupan,jam|nullable|date_format:H:i',
            'selesai' => 'required_if:cakupan,jam|nullable|date_format:H:i|after:mulai',
            'catatan' => 'nullable|string|max:255',
        ], [
            'tanggal.required' => 'Tanggal blokir wajib diisi.',
            'tanggal.after_or_equal' => 'Tanggal blokir tidak boleh di masa lalu.',
            'mulai.required_if' => 'Jam mulai wajib diisi.',
            'selesai.required_if' => 'Jam selesai wajib diisi.',
            'selesai.after' => 'Jam selesai harus setelah jam mulai.',
        ]);

        $whole = $data['cakupan'] === 'hari';
        StudioSlot::create([
            'date' => $data['tanggal'],
            'start_time' => $whole ? '00:00:00' : $data['mulai'] . ':00',
            'end_time' => $whole ? '23:59:00' : $data['selesai'] . ':00',
            'type' => 'blocked',
            'note' => $data['catatan'] ?? null,
        ]);

        // Peringatan bila ada sesi yang sudah terjadwal pada waktu yang diblokir
        $clash = Order::whereIn('session_status', ['Permintaan', 'Dikonfirmasi'])->whereDate('session_date', $data['tanggal'])->count();
        $resp = redirect()->route('admin.jadwal', ['tanggal' => $data['tanggal']])
            ->with('success', 'Studio berhasil diblokir pada ' . Studio::dateLabel($data['tanggal'], 'j F Y') . '. Pelanggan tidak dapat memesan pada waktu tersebut.');
        if ($clash > 0) {
            $resp->with('warning', "Ada {$clash} pesanan aktif pada tanggal ini. Blokir tidak membatalkannya; tinjau di halaman Pesanan.");
        }
        return $resp;
    }

    public function updateBlock(Request $request, $id)
    {
        $block = StudioSlot::where('type', 'blocked')->findOrFail($id);
        $data = $request->validate([
            'cakupan' => 'required|in:hari,jam',
            'mulai' => 'required_if:cakupan,jam|nullable|date_format:H:i',
            'selesai' => 'required_if:cakupan,jam|nullable|date_format:H:i|after:mulai',
            'catatan' => 'nullable|string|max:255',
        ], ['selesai.after' => 'Jam selesai harus setelah jam mulai.']);

        $whole = $data['cakupan'] === 'hari';
        $block->update([
            'start_time' => $whole ? '00:00:00' : $data['mulai'] . ':00',
            'end_time' => $whole ? '23:59:00' : $data['selesai'] . ':00',
            'note' => $data['catatan'] ?? null,
        ]);

        return redirect()->route('admin.jadwal', ['tanggal' => $block->date->toDateString()])
            ->with('success', 'Blokir jadwal berhasil diperbarui.');
    }

    public function destroyBlock($id)
    {
        $block = StudioSlot::where('type', 'blocked')->findOrFail($id);
        $date = $block->date->toDateString();
        $block->delete();

        return redirect()->route('admin.jadwal', ['tanggal' => $date])
            ->with('success', 'Blokir jadwal dihapus. Slot kembali dapat dipesan pelanggan.');
    }
}
