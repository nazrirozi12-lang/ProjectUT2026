<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Support\Studio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminPaymentController extends Controller
{
    private const MANUAL_METHODS = ['Tunai di Kasir Studio', 'EDC / Kartu Debit', 'Transfer Bank Langsung'];

    private function rupiah($n): string
    {
        return 'Rp' . number_format((int) $n, 0, ',', '.');
    }

    /**
     * Daftar pembayaran: tab, pencarian, filter sumber & bulan, halaman bernomor, metrik nyata.
     */
    public function index(Request $request)
    {
        $activeTab = $request->query('tab', 'semua');
        $search = trim((string) $request->query('q', ''));
        $source = $request->query('sumber');
        $month = $request->query('bulan');

        $unpaidOrders = fn () => Order::where('payment_status', 'Belum dibayar')
            ->whereNotIn('session_status', ['Dibatalkan', 'Diarsipkan']);

        $counts = [
            'semua' => Payment::count(),
            'bukti-terkirim' => Payment::where('status', 'Bukti terkirim')->count(),
            'belum-dibayar' => $unpaidOrders()->count(),
            'terverifikasi' => Payment::where('status', 'Terverifikasi')->count(),
            'perlu-perbaikan' => Payment::where('status', 'Perlu perbaikan')->count(),
        ];

        $today = Studio::today();
        $metrics = [
            'waiting' => ['count' => $counts['bukti-terkirim'], 'sum' => (int) Payment::where('status', 'Bukti terkirim')->sum('nominal')],
            'fix' => ['count' => $counts['perlu-perbaikan'], 'sum' => (int) Payment::where('status', 'Perlu perbaikan')->sum('nominal')],
            'verified' => [
                'count' => Payment::where('status', 'Terverifikasi')->whereYear('verified_at', $today->year)->whereMonth('verified_at', $today->month)->count(),
                'sum' => (int) Payment::where('status', 'Terverifikasi')->whereYear('verified_at', $today->year)->whereMonth('verified_at', $today->month)->sum('nominal'),
                'label' => Studio::dateLabel($today, 'F Y'),
            ],
            'unpaid' => ['count' => $counts['belum-dibayar'], 'sum' => (int) $unpaidOrders()->sum('total_price')],
        ];

        $oldest = Payment::where('status', 'Bukti terkirim')->orderBy('created_at')->first();

        // Pilihan filter
        $sources = Payment::query()->whereNotNull('bank_source')->distinct()->orderBy('bank_source')->pluck('bank_source')->all();
        $months = Payment::query()->pluck('created_at')->map(fn ($d) => $d->format('Y-m'))->push($today->format('Y-m'))->unique()->sort()->values()
            ->map(fn ($ym) => ['value' => $ym, 'label' => Studio::dateLabel($ym . '-01', 'F Y')])->all();

        if ($activeTab === 'belum-dibayar') {
            // Pesanan yang belum ada pembayaran sama sekali
            $query = $unpaidOrders()->latest('id');
            if ($search !== '') {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
                $query->where(fn ($q) => $q->whereLikeCi('order_code', $like)->orWhereLikeCi('invoice_number', $like)->orWhereLikeCi('customer_name', $like));
            }
            if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
                $query->whereYear('created_at', substr($month, 0, 4))->whereMonth('created_at', substr($month, 5, 2));
            }
            $paginator = $query->paginate(10)->withQueryString();
            $payments = $paginator->getCollection()->map(fn (Order $o) => [
                'id' => '—',
                'link' => route('admin.pesanan.detail', ['id' => $o->order_code]),
                'link_label' => 'Lihat pesanan →',
                'sender' => $o->customer_name,
                'invoice' => $o->invoice_number,
                'order_code' => $o->order_code,
                'nominal' => $this->rupiah($o->total_price),
                'received_date' => '—',
                'received_time' => 'Belum ada bukti',
                'status' => 'Belum dibayar',
            ])->all();
        } else {
            $query = Payment::with('order')->latest('id');
            match ($activeTab) {
                'bukti-terkirim' => $query->where('status', 'Bukti terkirim'),
                'terverifikasi' => $query->where('status', 'Terverifikasi'),
                'perlu-perbaikan' => $query->where('status', 'Perlu perbaikan'),
                default => null,
            };
            if ($search !== '') {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
                $query->where(function ($q) use ($like) {
                    $q->whereLikeCi('payment_code', $like)->orWhereLikeCi('sender_name', $like)
                        ->orWhereHas('order', fn ($o) => $o->whereLikeCi('invoice_number', $like)->orWhereLikeCi('order_code', $like));
                });
            }
            if ($source) {
                $query->where('bank_source', $source);
            }
            if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
                $query->whereYear('created_at', substr($month, 0, 4))->whereMonth('created_at', substr($month, 5, 2));
            }
            $paginator = $query->paginate(10)->withQueryString();
            $payments = $paginator->getCollection()->map(fn (Payment $p) => [
                'id' => $p->payment_code,
                'link' => route('admin.pembayaran.detail', ['id' => $p->payment_code]),
                'link_label' => 'Tinjau →',
                'sender' => $p->sender_name,
                'invoice' => $p->order ? $p->order->invoice_number : '-',
                'order_code' => $p->order ? $p->order->order_code : '-',
                'nominal' => $this->rupiah($p->nominal),
                'received_date' => Studio::dateLabel($p->created_at, 'd M'),
                'received_time' => $p->created_at->format('H.i'),
                'status' => $p->status,
            ])->all();
        }

        $oldestLabel = $oldest ? Studio::dateLabel($oldest->created_at, 'j F') : null;

        return view('admin.pembayaran', compact('payments', 'activeTab', 'counts', 'metrics', 'paginator', 'search', 'source', 'month', 'sources', 'months', 'oldestLabel'))
            ->with('manualMethods', self::MANUAL_METHODS);
    }

    /** Jenis berkas bukti & ketersediaannya. */
    private function proofInfo(Payment $payment): array
    {
        $none = ['available' => false, 'kind' => 'none', 'url' => null];
        if (!$payment->proof_file) {
            return $none;
        }

        $ext = strtolower((string) $payment->file_extension);
        $kind = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? 'image' : ($ext === 'pdf' ? 'pdf' : 'other');

        if (config('filesystems.proof_disk') === 'database') {
            $row = $payment->proof()->first(['id', 'mime']);
            if (!$row) {
                return $none;
            }
            $kind = str_starts_with($row->mime, 'image/') ? 'image' : ($row->mime === 'application/pdf' ? 'pdf' : 'other');
            return ['available' => true, 'kind' => $kind, 'url' => route('admin.pembayaran.bukti', $payment->payment_code)];
        }

        try {
            $disk = Storage::disk(config('filesystems.proof_disk'));
            return $disk->exists($payment->proof_file)
                ? ['available' => true, 'kind' => $kind, 'url' => $disk->url($payment->proof_file)]
                : $none;
        } catch (\Throwable $e) {
            return $none;
        }
    }

    /**
     * Tampilkan berkas bukti transfer yang disimpan di database (khusus admin).
     */
    public function bukti($id)
    {
        $payment = Payment::findByCodeOrFail($id);
        $payment->load('proof');
        abort_unless($payment->proof, 404);

        $mime = $payment->proof->mime;
        $inline = preg_match('#^(image/(png|jpe?g|gif|webp)|application/pdf)$#', $mime) === 1;

        return response(base64_decode($payment->proof->data), 200, [
            'Content-Type' => $inline ? $mime : 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment') . '; filename="' . addslashes($payment->file_original_name ?? 'bukti-transfer') . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Detail pembayaran: data nyata, pratinjau dokumen, riwayat tinjauan.
     */
    public function show($id)
    {
        $paymentModel = Payment::findByCodeOrFail($id);
        $paymentModel->load('order.package');
        $order = $paymentModel->order;
        $proof = $this->proofInfo($paymentModel);

        $history = [];
        $history[] = Studio::dateLabel($paymentModel->created_at, 'j M, H.i') . ' · ' . $paymentModel->sender_name . ' mengirim bukti dan data transfer.';
        if ($paymentModel->status === 'Terverifikasi') {
            $history[] = Studio::dateLabel($paymentModel->verified_at ?? $paymentModel->updated_at, 'j M, H.i') . ' · Admin memverifikasi pembayaran.';
        } elseif ($paymentModel->status === 'Perlu perbaikan') {
            $history[] = Studio::dateLabel($paymentModel->updated_at, 'j M, H.i') . ' · Admin meminta perbaikan bukti.';
        }

        $statusBadge = match ($paymentModel->status) {
            'Terverifikasi' => 'Terverifikasi',
            'Perlu perbaikan' => 'Perlu perbaikan bukti',
            default => 'Bukti terkirim · Belum terverifikasi',
        };

        $invoiceTotal = $order ? (int) $order->total_price : null;
        $matches = $invoiceTotal !== null && $invoiceTotal === (int) $paymentModel->nominal;

        $payment = [
            'id' => $paymentModel->payment_code,
            'sender' => $paymentModel->sender_name,
            'status' => $paymentModel->status,
            'status_badge' => $statusBadge,
            'file_name' => $paymentModel->file_original_name ?: 'Tanpa berkas (dicatat manual)',
            'file_meta' => $paymentModel->proof_file ? (strtoupper((string) $paymentModel->file_extension) . ' · Bukti unggahan pelanggan') : 'Pembayaran dicatat admin',
            'proof' => $proof,
            'has_proof_file' => (bool) $paymentModel->proof_file,
            'transfer_date' => Studio::dateLabel($paymentModel->transfer_date, 'j F Y'),
            'bank_source' => $paymentModel->bank_source ?? '-',
            'nominal' => $this->rupiah($paymentModel->nominal),
            'sender_note' => $paymentModel->sender_notes ?: 'Tidak ada catatan dari pengirim.',
            'submitted_at' => Studio::dateLabel($paymentModel->created_at, 'j M Y, H.i') . ' WIB',
            'invoice' => $order ? $order->invoice_number : '-',
            'order_code' => $order ? $order->order_code : '-',
            'package_name' => $order ? $order->package_name : '-',
            'invoice_total' => $invoiceTotal !== null ? $this->rupiah($invoiceTotal) : '-',
            'nominal_matches' => $matches,
            'session_schedule' => $order && $order->session_date ? Studio::dateLabel($order->session_date, 'j M Y') . ' · ' . $order->session_time_slot : '-',
            'internal_note' => $paymentModel->internal_review_notes ?: '',
            'customer_message' => $paymentModel->customer_message,
            'can_review' => $paymentModel->status !== 'Terverifikasi',
            'history' => $history,
            'decision' => match ($paymentModel->status) {
                'Terverifikasi' => 'Terverifikasi',
                'Perlu perbaikan' => 'Perlu perbaikan',
                default => 'Belum ada keputusan',
            },
        ];

        return view('admin.pembayaran-detail', compact('payment'));
    }

    /** Verifikasi pembayaran. Tidak mengonfirmasi sesi (dilakukan terpisah di Pesanan). */
    public function verifikasi(Request $request, $id)
    {
        $payment = Payment::findByCodeOrFail($id);
        $data = $request->validate([
            'konfirmasi' => 'accepted',
            'catatan' => 'nullable|string|max:500',
        ], ['konfirmasi.accepted' => 'Centang pernyataan peninjauan sebelum memverifikasi pembayaran.']);

        if ($payment->status === 'Terverifikasi') {
            return redirect()->route('admin.pembayaran.detail', ['id' => $payment->payment_code])
                ->with('info', 'Pembayaran ini sudah terverifikasi sebelumnya.');
        }

        $payment->update([
            'status' => 'Terverifikasi',
            'verified_at' => now(),
            'internal_review_notes' => $data['catatan'] ?? $payment->internal_review_notes,
            'customer_message' => null,
        ]);
        if ($payment->order) {
            $payment->order->update(['payment_status' => 'Terverifikasi']);
        }

        return redirect()->route('admin.pembayaran.detail', ['id' => $payment->payment_code])
            ->with('success', 'Pembayaran ' . $payment->payment_code . ' berhasil diverifikasi. Jadwal sesi tetap perlu dikonfirmasi di halaman Pesanan.');
    }

    /** Minta pelanggan memperbaiki bukti. */
    public function mintaPerbaikan(Request $request, $id)
    {
        $payment = Payment::findByCodeOrFail($id);
        $data = $request->validate([
            'alasan' => 'required|string|max:200',
            'pesan' => 'required|string|min:5|max:500',
        ], [
            'alasan.required' => 'Pilih alasan perbaikan.',
            'pesan.required' => 'Pesan instruksi untuk pelanggan wajib diisi.',
            'pesan.min' => 'Pesan instruksi terlalu singkat.',
        ]);

        if ($payment->status === 'Terverifikasi') {
            return back()->with('error', 'Pembayaran yang sudah terverifikasi tidak dapat diminta perbaikan.');
        }

        $payment->update([
            'status' => 'Perlu perbaikan',
            'customer_message' => $data['pesan'],
            'internal_review_notes' => trim($data['alasan'] . ($payment->internal_review_notes ? "\n" . $payment->internal_review_notes : '')),
        ]);
        if ($payment->order) {
            $payment->order->update(['payment_status' => 'Perlu perbaikan']);
        }

        return redirect()->route('admin.pembayaran.detail', ['id' => $payment->payment_code])
            ->with('success', 'Permintaan perbaikan bukti untuk ' . $payment->payment_code . ' berhasil disimpan.');
    }

    /** Simpan catatan internal admin. */
    public function simpanCatatan(Request $request, $id)
    {
        $payment = Payment::findByCodeOrFail($id);
        $data = $request->validate(['catatan' => 'nullable|string|max:1000']);
        $payment->update(['internal_review_notes' => $data['catatan'] ?? null]);

        return redirect()->route('admin.pembayaran.detail', ['id' => $payment->payment_code])
            ->with('success', 'Catatan pembayaran berhasil diperbarui.');
    }

    /** Catat pembayaran manual (tunai/EDC/transfer langsung) -> langsung terverifikasi. */
    public function catatManual(Request $request)
    {
        $data = $request->validate([
            'invoice' => 'required|string|exists:orders,invoice_number',
            'metode' => 'required|in:' . implode(',', self::MANUAL_METHODS),
            'nominal' => 'required|string',
            'tanggal' => 'required|date',
            'catatan' => 'nullable|string|max:500',
        ], [
            'invoice.required' => 'Nomor invoice wajib diisi.',
            'invoice.exists' => 'Nomor invoice tidak ditemukan. Periksa kembali di halaman Pesanan.',
            'metode.required' => 'Pilih metode penerimaan.',
            'nominal.required' => 'Nominal diterima wajib diisi.',
            'tanggal.required' => 'Tanggal penerimaan wajib diisi.',
        ]);

        $nominal = (int) preg_replace('/\D+/', '', $data['nominal']);
        if ($nominal < 1) {
            return back()->withInput()->withErrors(['nominal' => 'Nominal diterima harus lebih dari 0.']);
        }

        $order = Order::where('invoice_number', $data['invoice'])->firstOrFail();

        do {
            $code = 'BYR-' . date('Y') . '-' . strtoupper(Str::random(5));
        } while (Payment::where('payment_code', $code)->exists());

        $payment = Payment::create([
            'payment_code' => $code,
            'order_id' => $order->id,
            'sender_name' => $order->customer_name,
            'transfer_date' => $data['tanggal'],
            'bank_source' => $data['metode'],
            'nominal' => $nominal,
            'sender_notes' => $data['catatan'] ?? null,
            'proof_file' => null,
            'file_extension' => '-',
            'status' => 'Terverifikasi',
            'verified_at' => now(),
            'internal_review_notes' => 'Dicatat manual oleh admin.',
        ]);
        $order->update(['payment_status' => 'Terverifikasi']);

        return redirect()->route('admin.pembayaran.detail', ['id' => $payment->payment_code])
            ->with('success', 'Pembayaran manual ' . $payment->payment_code . ' berhasil dicatat dan diverifikasi.');
    }
}
