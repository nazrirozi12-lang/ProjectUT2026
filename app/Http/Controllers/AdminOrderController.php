<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Studio;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminOrderController extends Controller
{
    private const PAYMENT_STATUSES = ['Belum dibayar', 'Bukti terkirim', 'Terverifikasi', 'Perlu perbaikan', 'Refund'];

    /**
     * Daftar pesanan: tab status, pencarian, filter pembayaran & bulan, halaman bernomor.
     */
    public function index(Request $request)
    {
        $activeTab = $request->query('tab', 'semua');
        $search = trim((string) $request->query('q', ''));
        $payment = $request->query('pembayaran');
        $month = $request->query('bulan');

        $base = Order::query();

        // Hitungan tab nyata (tidak terpengaruh tab aktif)
        $counts = [
            'semua' => Order::where('session_status', '!=', 'Diarsipkan')->count(),
            'permintaan' => Order::where('session_status', 'Permintaan')->count(),
            'dikonfirmasi' => Order::where('session_status', 'Dikonfirmasi')->count(),
            'selesai' => Order::where('session_status', 'Selesai')->count(),
            'dibatalkan' => Order::where('session_status', 'Dibatalkan')->count(),
            'arsip' => Order::where('session_status', 'Diarsipkan')->count(),
        ];

        $query = Order::query()->with(['customer', 'package'])->latest('id');

        match ($activeTab) {
            'permintaan' => $query->where('session_status', 'Permintaan'),
            'dikonfirmasi' => $query->where('session_status', 'Dikonfirmasi'),
            'selesai' => $query->where('session_status', 'Selesai'),
            'dibatalkan' => $query->where('session_status', 'Dibatalkan'),
            'arsip' => $query->where('session_status', 'Diarsipkan'),
            default => $query->where('session_status', '!=', 'Diarsipkan'),
        };

        if ($search !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereLikeCi('order_code', $like)
                    ->orWhereLikeCi('customer_name', $like)
                    ->orWhereLikeCi('customer_email', $like)
                    ->orWhereLikeCi('package_name', $like);
            });
        }

        if ($payment && in_array($payment, self::PAYMENT_STATUSES, true)) {
            $query->where('payment_status', $payment);
        }

        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $query->whereYear('session_date', substr($month, 0, 4))->whereMonth('session_date', substr($month, 5, 2));
        }

        $paginator = $query->paginate(10)->withQueryString();

        $orders = $paginator->getCollection()->map(function (Order $order) {
            return [
                'id' => $order->id,
                'code' => $order->order_code,
                'customer' => $order->customer_name,
                'package' => $order->package_name,
                'value' => 'Rp' . number_format($order->total_price, 0, ',', '.'),
                'date' => $order->session_date ? Studio::dateLabel($order->session_date, 'd M Y') : '-',
                'time' => explode(' ', (string) $order->session_time_slot)[0] ?? '-',
                'session_status' => $order->session_status,
                'payment_status' => $order->payment_status,
                'deletable' => $order->isDeletable(),
            ];
        })->all();

        // Pilihan bulan: bulan yang punya sesi + bulan ini
        $months = Order::query()->whereNotNull('session_date')->pluck('session_date')
            ->map(fn ($d) => $d->format('Y-m'))->push(Studio::today()->format('Y-m'))->unique()->sort()->values()
            ->map(fn ($ym) => ['value' => $ym, 'label' => Studio::dateLabel($ym . '-01', 'F Y')])->all();

        $summary = [
            'month' => Order::whereYear('created_at', Studio::today()->year)->whereMonth('created_at', Studio::today()->month)->count(),
            'review' => Order::where('payment_status', 'Bukti terkirim')->count(),
            'approved' => $counts['dikonfirmasi'],
        ];

        return view('admin.pesanan', compact('orders', 'activeTab', 'counts', 'paginator', 'search', 'payment', 'month', 'months', 'summary'))
            ->with('paymentStatuses', self::PAYMENT_STATUSES);
    }

    /**
     * Detail pesanan: data nyata + linimasa dari riwayat status.
     */
    public function show($id)
    {
        $orderModel = Order::findByCodeOrFail($id);
        $orderModel->load(['customer', 'package', 'payments']);

        $latestPayment = $orderModel->payments->sortByDesc('id')->first();
        $package = $orderModel->package;

        $paymentSummary = match ($orderModel->payment_status) {
            'Terverifikasi' => 'Terverifikasi · Lunas',
            'Bukti terkirim' => 'Bukti terkirim · Belum terverifikasi',
            'Perlu perbaikan' => 'Perlu perbaikan bukti transfer',
            default => $orderModel->payment_status,
        };

        $status = $orderModel->session_status;
        $paid = $orderModel->payment_status === 'Terverifikasi';

        $order = [
            'id' => $orderModel->id,
            'code' => $orderModel->order_code,
            'customer' => $orderModel->customer_name,
            'customer_code' => $orderModel->customer ? $orderModel->customer->customer_code : '-',
            'email' => $orderModel->customer_email,
            'phone' => $orderModel->customer_whatsapp,
            'created_date' => Studio::dateLabel($orderModel->created_at, 'j F Y'),
            'package_name' => $orderModel->package_name,
            'package_image' => $package ? $package->imageSource() : 'paket-1.jpg',
            'package_desc' => $package ? (string) $package->description : '',
            'package_price' => 'Rp' . number_format($orderModel->total_price, 0, ',', '.'),
            'date_formatted' => $orderModel->session_date ? Studio::dateLabel($orderModel->session_date, 'l, j F Y') : '-',
            'date_input' => $orderModel->session_date ? $orderModel->session_date->format('Y-m-d') : '',
            'time_formatted' => $orderModel->session_time_slot,
            'time_start' => (Studio::parseRange($orderModel->session_time_slot) ? Studio::fromMinutes(Studio::parseRange($orderModel->session_time_slot)[0]) : ''),
            'location' => $orderModel->location,
            'features' => $package && is_array($package->features) ? $package->features : [],
            'customer_note' => $orderModel->customer_notes ?: 'Tidak ada catatan tambahan dari pemesan.',
            'internal_note' => $orderModel->internal_notes ?: '',
            'invoice_number' => $orderModel->invoice_number,
            'payment_summary' => $paymentSummary,
            'payment_status_badge' => $orderModel->payment_status,
            'session_status' => $status,
            'submitted_nominal' => $latestPayment ? 'Rp' . number_format($latestPayment->nominal, 0, ',', '.') : 'Belum ada',
            'submitted_date' => $latestPayment && $latestPayment->transfer_date ? Studio::dateLabel($latestPayment->transfer_date, 'j M Y') : '-',
            'payment_code' => $latestPayment ? $latestPayment->payment_code : null,
            'updated_note' => 'Diperbarui ' . Studio::dateLabel($orderModel->updated_at, 'j M Y, H.i') . ' WIB',
            // Tombol aksi sesuai status nyata
            'can_confirm' => $status === 'Permintaan' && $paid,
            'confirm_hint' => $status === 'Permintaan' && !$paid ? 'Verifikasi pembayaran terlebih dahulu sebelum mengonfirmasi sesi.' : null,
            'can_complete' => $status === 'Dikonfirmasi',
            'can_cancel' => in_array($status, ['Permintaan', 'Dikonfirmasi'], true),
            'can_edit' => in_array($status, ['Permintaan', 'Dikonfirmasi'], true),
            'can_archive' => in_array($status, ['Selesai', 'Dibatalkan'], true),
            'can_delete' => $orderModel->isDeletable(),
            'delete_hint' => $orderModel->isDeletable() ? null : 'Pesanan dengan sesi Dikonfirmasi atau Selesai tidak dapat dihapus. Batalkan terlebih dahulu bila perlu.',
            'is_cancelled' => $status === 'Dibatalkan',
            'cancel_reason' => $orderModel->cancel_reason,
            'timeline' => $this->timeline($orderModel, $latestPayment),
            'slots' => Studio::slots(),
        ];

        return view('admin.pesanan-detail', compact('order'));
    }

    /** Linimasa pesanan dari data nyata (tanggal dibuat, bukti, verifikasi, konfirmasi, selesai). */
    private function timeline(Order $o, $payment): array
    {
        $d = fn ($ts) => $ts ? Studio::dateLabel($ts, 'j M') : '';
        $steps = [];

        $steps[] = ['state' => 'complete', 'text' => $d($o->created_at) . ' · Pesanan dibuat'];

        $firstPayment = $o->payments->sortBy('id')->first();
        $steps[] = $firstPayment
            ? ['state' => 'complete', 'text' => $d($firstPayment->created_at) . ' · Bukti pembayaran diterima']
            : ['state' => 'pending', 'text' => 'Bukti pembayaran · Menunggu pelanggan'];

        if ($o->payment_status === 'Terverifikasi') {
            $steps[] = ['state' => 'complete', 'text' => $d($payment?->verified_at ?? $o->updated_at) . ' · Pembayaran terverifikasi'];
        } elseif ($o->payment_status === 'Perlu perbaikan') {
            $steps[] = ['state' => 'warn', 'text' => 'Verifikasi pembayaran · Bukti perlu diperbaiki pelanggan'];
        } elseif ($o->payment_status === 'Bukti terkirim') {
            $steps[] = ['state' => 'pending', 'text' => 'Verifikasi pembayaran · Menunggu tinjauan admin'];
        } else {
            $steps[] = ['state' => 'pending', 'text' => 'Verifikasi pembayaran · Menunggu'];
        }

        if ($o->session_status === 'Dibatalkan') {
            $steps[] = ['state' => 'cancelled', 'text' => $d($o->cancelled_at ?? $o->updated_at) . ' · Pesanan dibatalkan'];
        } else {
            if (in_array($o->session_status, ['Dikonfirmasi', 'Selesai', 'Diarsipkan'], true)) {
                $steps[] = ['state' => 'complete', 'text' => $d($o->confirmed_at ?? $o->updated_at) . ' · Jadwal dikonfirmasi'];
            } else {
                $steps[] = ['state' => 'pending', 'text' => 'Konfirmasi jadwal · Menunggu'];
            }
            if (in_array($o->session_status, ['Selesai', 'Diarsipkan'], true)) {
                $steps[] = ['state' => 'complete', 'text' => $d($o->completed_at ?? $o->updated_at) . ' · Sesi selesai'];
            } else {
                $steps[] = ['state' => 'pending', 'text' => 'Sesi selesai · Belum berlangsung'];
            }
        }

        if ($o->session_status === 'Diarsipkan') {
            $steps[] = ['state' => 'complete', 'text' => $d($o->archived_at ?? $o->updated_at) . ' · Pesanan diarsipkan'];
        }

        return $steps;
    }

    /** Konfirmasi sesi: hanya bila pembayaran sudah terverifikasi. */
    public function konfirmasiSesi($id)
    {
        $order = Order::findByCodeOrFail($id);

        if ($order->session_status !== 'Permintaan') {
            return back()->with('error', 'Sesi ini tidak berstatus Permintaan, sehingga tidak dapat dikonfirmasi.');
        }
        if ($order->payment_status !== 'Terverifikasi') {
            return back()->with('error', 'Sesi belum dapat dikonfirmasi: verifikasi pembayaran terlebih dahulu.');
        }

        $order->update(['session_status' => 'Dikonfirmasi', 'confirmed_at' => now()]);

        return redirect()->route('admin.pesanan.detail', ['id' => $order->order_code])
            ->with('success', 'Sesi pesanan ' . $order->order_code . ' berhasil dikonfirmasi.');
    }

    /** Tandai sesi selesai. */
    public function selesaikanSesi($id)
    {
        $order = Order::findByCodeOrFail($id);

        if ($order->session_status !== 'Dikonfirmasi') {
            return back()->with('error', 'Hanya sesi yang sudah dikonfirmasi yang dapat ditandai selesai.');
        }

        $order->update(['session_status' => 'Selesai', 'completed_at' => now()]);

        return redirect()->route('admin.pesanan.detail', ['id' => $order->order_code])
            ->with('success', 'Sesi pesanan ' . $order->order_code . ' ditandai selesai.');
    }

    /** Batalkan pesanan (alasan wajib). Jadwal dilepas karena status tidak lagi memakai slot. */
    public function batalkanSesi(Request $request, $id)
    {
        $order = Order::findByCodeOrFail($id);
        $data = $request->validate(['alasan' => 'required|string|min:3|max:500'], [
            'alasan.required' => 'Alasan pembatalan wajib diisi.',
            'alasan.min' => 'Alasan pembatalan terlalu singkat.',
        ]);

        if (!in_array($order->session_status, ['Permintaan', 'Dikonfirmasi'], true)) {
            return back()->with('error', 'Pesanan dengan status ' . $order->session_status . ' tidak dapat dibatalkan.');
        }

        $order->update([
            'session_status' => 'Dibatalkan',
            'cancelled_at' => now(),
            'cancel_reason' => $data['alasan'],
            'internal_notes' => trim(($order->internal_notes ? $order->internal_notes . "\n" : '') . 'Dibatalkan: ' . $data['alasan']),
        ]);

        return redirect()->route('admin.pesanan.detail', ['id' => $order->order_code])
            ->with('success', 'Pesanan ' . $order->order_code . ' berhasil dibatalkan.');
    }

    /** Arsipkan pesanan yang sudah selesai atau dibatalkan. */
    public function arsipkanSesi($id)
    {
        $order = Order::findByCodeOrFail($id);

        if (!in_array($order->session_status, ['Selesai', 'Dibatalkan'], true)) {
            return back()->with('error', 'Pesanan aktif belum dapat diarsipkan. Selesaikan atau batalkan terlebih dahulu.');
        }

        $order->update(['session_status' => 'Diarsipkan', 'archived_at' => now()]);

        return redirect()->route('admin.pesanan', ['tab' => 'arsip'])
            ->with('success', 'Pesanan ' . $order->order_code . ' berhasil dipindahkan ke arsip.');
    }

    /** Hapus pesanan permanen. Hanya untuk sesi selain Dikonfirmasi dan Selesai. */
    public function destroy($id)
    {
        $order = Order::findByCodeOrFail($id);
        $code = $order->order_code;

        if (!$order->isDeletable()) {
            return back()->with('error', 'Pesanan ' . $code . ' tidak dapat dihapus karena sesinya berstatus Dikonfirmasi atau Selesai.');
        }
        if ($order->gallery()->exists()) {
            return back()->with('error', 'Pesanan ' . $code . ' sudah memiliki galeri. Hapus galerinya terlebih dahulu di menu Galeri.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
            // Pembayaran (beserta bukti) ikut terhapus; jadwal terkait dilepas
            $order->studioSlots()->delete();
            $order->delete();
        });

        return redirect()->route('admin.pesanan')->with('success', 'Pesanan ' . $code . ' beserta data pembayarannya berhasil dihapus.');
    }

    /** Edit jadwal, lokasi, dan catatan internal. */
    public function update(Request $request, $id)
    {
        $order = Order::findByCodeOrFail($id);

        if (!in_array($order->session_status, ['Permintaan', 'Dikonfirmasi'], true)) {
            return back()->with('error', 'Pesanan dengan status ' . $order->session_status . ' tidak dapat diedit.');
        }

        $data = $request->validate([
            'session_date' => ['required', 'date_format:Y-m-d'],
            'session_time' => ['required', Rule::in(Studio::slots())],
            'location' => 'required|string|max:255',
            'internal_notes' => 'nullable|string|max:2000',
        ], [
            'session_date.required' => 'Tanggal sesi wajib diisi.',
            'session_date.date_format' => 'Format tanggal sesi tidak valid.',
            'session_time.required' => 'Jam sesi wajib dipilih.',
            'session_time.in' => 'Jam sesi tidak tersedia.',
            'location.required' => 'Lokasi sesi wajib diisi.',
        ]);

        $range = Studio::parseRange($order->session_time_slot);
        $duration = $order->package && $order->package->duration_minutes
            ? (int) $order->package->duration_minutes
            : ($range ? $range[1] - $range[0] : 90);

        $changed = $order->session_date->toDateString() !== $data['session_date']
            || ($range && Studio::fromMinutes($range[0]) !== $data['session_time']);

        if ($changed) {
            // Tanggal boleh di masa depan saja; slot tidak boleh bentrok dengan pesanan/blokir lain
            $map = Studio::availability(
                \Carbon\Carbon::parse($data['session_date'], config('app.timezone'))->startOfDay(),
                \Carbon\Carbon::parse($data['session_date'], config('app.timezone'))->startOfDay(),
                $duration,
                $order->id
            );
            if (($map[$data['session_date']][$data['session_time']] ?? null) !== 'available') {
                return back()->withInput()->withErrors(['session_date' => 'Jadwal baru bentrok dengan pesanan lain atau studio sedang diblokir.']);
            }
        }

        $order->update([
            'session_date' => $data['session_date'],
            'session_time_slot' => Studio::slotLabel($data['session_time'], $duration),
            'location' => $data['location'],
            'internal_notes' => $data['internal_notes'] ?? null,
        ]);

        return redirect()->route('admin.pesanan.detail', ['id' => $order->order_code])
            ->with('success', 'Perubahan pesanan ' . $order->order_code . ' berhasil disimpan.');
    }
}
