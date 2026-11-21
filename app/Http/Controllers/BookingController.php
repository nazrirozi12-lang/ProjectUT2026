<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\StudioSlot;
use App\Support\Studio;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    /**
     * Tampilkan halaman formulir pemesanan publik
     */
    public function create(Request $request)
    {
        $dbPackages = Package::where('is_active', true)->get();

        $packages = $dbPackages->map(function ($pkg) {
            return [
                'id' => $pkg->slug,
                'code' => $pkg->code,
                'name' => $pkg->name,
                'price' => 'Rp' . number_format($pkg->price, 0, ',', '.'),
                'raw_price' => $pkg->price,
                'duration' => $pkg->duration_minutes ?? 60,
                'duration_label' => $pkg->duration,
                'location_note' => 'Lokasi sesi dibahas saat perencanaan. Biaya transport di luar harga paket.',
                'note' => $pkg->tagline,
                'image' => $pkg->imageSource(),
                'desc' => $pkg->description,
                'items' => is_array($pkg->features) ? $pkg->features : [],
            ];
        })->toArray();

        // Fallback jika database paket belum lengkap
        if (empty($packages)) {
            $locNote = 'Lokasi sesi dibahas saat perencanaan. Biaya transport di luar harga paket.';
            $packages = [
                [
                    'id' => 'birthday-family-photoshoot', 'name' => 'Birthday/Family Photoshoot',
                    'price' => 'Rp1.250.000', 'raw_price' => 1250000, 'duration' => 120, 'duration_label' => '2 jam',
                    'location_note' => $locNote, 'note' => 'Di luar transport', 'image' => 'paket-1.jpg', 'desc' => '',
                    'items' => ['Durasi Foto: 2 Jam', '30 Edited Photo', '5 Photo Express', 'All Photo via Gdrive', 'Pengerjaan: 5-7 hari kerja'],
                ],
                [
                    'id' => 'prewedding', 'name' => 'Prewedding',
                    'price' => 'Rp2.000.000', 'raw_price' => 2000000, 'duration' => 120, 'duration_label' => '2 jam',
                    'location_note' => $locNote, 'note' => 'Di luar fee transport', 'image' => 'paket-2.jpg', 'desc' => '',
                    'items' => ['Durasi Foto: 2 Jam', '30 Edited Photo', 'All Photo via Google Drive', 'Pengerjaan: 7-10 hari kerja'],
                ],
                [
                    'id' => 'graduation', 'name' => 'Graduation',
                    'price' => 'Rp700.000', 'raw_price' => 700000, 'duration' => 60, 'duration_label' => '1 jam',
                    'location_note' => $locNote, 'note' => 'Di luar transport', 'image' => 'paket-3.jpg', 'desc' => '',
                    'items' => ['Durasi Foto: 1 Jam', '15 Edited Photo', 'All Photo (Preview) via Gdrive', 'Unlimited Photo (khusus foto di studio)', 'Pengerjaan: 5-7 hari kerja'],
                ],
            ];
        }

        // Paket terpilih: dari ?package=..., bila tidak cocok pakai paket kedua (atau pertama)
        $selectedPackageId = $request->query('package');
        if (!collect($packages)->contains('id', $selectedPackageId)) {
            $selectedPackageId = ($packages[1]['id'] ?? ($packages[0]['id'] ?? null));
        }

        // Data kalender (nyata, mengikuti tanggal hari ini WIB) untuk tiap durasi paket
        $from = Studio::minDate();
        $to = Studio::maxDate();
        $availability = [];
        foreach (collect($packages)->pluck('duration')->unique() as $duration) {
            $availability[(string) $duration] = Studio::availability($from, $to, (int) $duration);
        }

        $calendar = [
            'today' => Studio::today()->toDateString(),
            'min' => $from->toDateString(),
            'max' => $to->toDateString(),
            'slots' => Studio::slots(),
            'availability' => $availability,
            'oldDate' => old('session_date'),
            'oldTime' => old('session_time'),
        ];

        return view('pemesanan', compact('packages', 'selectedPackageId', 'calendar'));
    }

    /**
     * Proses penyimpanan pemesanan baru ke database
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'package_id' => ['required', 'string', Rule::exists('packages', 'slug')->where('is_active', true)],
            'session_date' => ['required', 'date_format:Y-m-d'],
            'session_time' => ['required', 'string'],
            'nama_lengkap' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[\pL][\pL\s.\x27\-]*$/u'],
            'email' => 'required|email:rfc|max:255',
            'whatsapp' => ['required', 'string', 'max:25', 'regex:/^\+?[0-9][0-9\s\-]{6,22}[0-9]$/', function ($attribute, $value, $fail) {
                $digits = strlen(preg_replace('/\D+/', '', (string) $value));
                if ($digits < 9 || $digits > 15) {
                    $fail('Nomor WhatsApp harus terdiri dari 9–15 digit.');
                }
            }],
            'lokasi' => 'required|string|min:3|max:255',
            'catatan' => 'nullable|string|max:2000',
            'persetujuan' => 'accepted',
        ], [
            'nama_lengkap.min' => 'Nama lengkap minimal 3 karakter.',
            'nama_lengkap.max' => 'Nama lengkap terlalu panjang (maksimal 100 karakter).',
            'nama_lengkap.regex' => 'Nama lengkap hanya boleh berisi huruf, spasi, titik, apostrof, atau tanda hubung.',
            'whatsapp.regex' => 'Format nomor WhatsApp tidak valid. Contoh: 0812 3456 7890 atau +62 812 3456 7890.',
            'whatsapp.max' => 'Nomor WhatsApp terlalu panjang.',
            'lokasi.required' => 'Lokasi sesi wajib diisi.',
            'lokasi.min' => 'Lokasi sesi minimal 3 karakter.',
            'persetujuan.accepted' => 'Centang persetujuan Kebijakan Privasi dan Syarat Pemesanan untuk melanjutkan.',
            'package_id.required' => 'Pilih salah satu paket layanan.',
            'package_id.exists' => 'Paket yang dipilih tidak tersedia.',
            'session_date.required' => 'Pilih tanggal sesi pada kalender.',
            'session_date.date_format' => 'Format tanggal sesi tidak valid.',
            'session_time.required' => 'Pilih jam sesi yang tersedia.',
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'whatsapp.required' => 'Nomor WhatsApp wajib diisi.',
        ]);

        $packageSlug = $validated['package_id'];
        $package = Package::where('slug', $packageSlug)->firstOrFail();

        $duration = (int) ($package->duration_minutes ?: 60);

        // Pastikan jadwal masih tersedia (bukan tanggal lampau, tidak bentrok, tidak diblokir)
        if (!Studio::isAvailable($validated['session_date'], $validated['session_time'], $duration)) {
            return back()->withInput()->withErrors([
                'session_date' => 'Jadwal yang Anda pilih sudah tidak tersedia. Silakan pilih tanggal atau jam lain.',
            ]);
        }

        $packageName = $package->name;
        $packagePrice = $package->price;
        $packagePriceFormatted = 'Rp' . number_format($packagePrice, 0, ',', '.');
        $packageImage = $package->imageSource();
        $packageDesc = (string) $package->description;
        $packageItems = is_array($package->features) ? $package->features : [];
        $lokasiHint = 'Lokasi sesi dibahas saat perencanaan. Biaya transport di luar harga paket.';

        // 1. Simpan atau perbarui Customer
        $customer = Customer::firstOrNew(['email' => $validated['email']]);
        if (!$customer->exists) {
            $customer->customer_code = 'CUST-' . date('Y') . '-' . strtoupper(Str::random(4));
        }
        $customer->fill([
            'name' => $validated['nama_lengkap'],
            'whatsapp' => $validated['whatsapp'],
            'status' => 'Aktif',
        ])->save();

        // 2. Generate Nomor Unik
        do {
            $suffix = strtoupper(Str::random(5));
            $orderCode = 'CH-' . date('ymd') . '-' . $suffix;
        } while (Order::where('order_code', $orderCode)->exists());
        $invoiceNumber = 'INV-CH-' . date('Y') . '-' . $suffix;

        $sessionDateSql = $validated['session_date'];
        $timeSlotLabel = Studio::slotLabel($validated['session_time'], $duration);

        // 3. Simpan Order ke Database
        $order = Order::create([
            'order_code' => $orderCode,
            'invoice_number' => $invoiceNumber,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_whatsapp' => $customer->whatsapp,
            'package_name' => $packageName,
            'total_price' => $packagePrice,
            'session_date' => $sessionDateSql,
            'session_time_slot' => $timeSlotLabel,
            'location' => $validated['lokasi'],
            'customer_notes' => $validated['catatan'] ?? null,
            'session_status' => 'Permintaan',
            'payment_status' => 'Belum dibayar',
        ]);

        // Simpan ringkasan ke session untuk aliran checkout tanpa login
        session(['last_booking' => \App\Support\BookingSummary::fromOrder($order)]);

        return redirect()->route('checkout')
            ->with('success', 'Permintaan pemesanan berhasil dikirim. Kode pesanan Anda: ' . $order->order_code);
    }

    /**
     * Tampilkan halaman Checkout (hanya untuk pemesan pada sesi yang sama)
     */
    public function checkout()
    {
        $data = session('last_booking');

        if (!$data) {
            return redirect()->route('pemesanan')
                ->with('error', 'Sesi pemesanan tidak ditemukan. Silakan isi formulir pemesanan terlebih dahulu.');
        }

        // Status nyata (real-time) dari database
        $order = Order::with('latestPayment')->where('order_code', $data['order_code'] ?? '')->first();
        if (!$order) {
            session()->forget('last_booking');
            return redirect()->route('pemesanan')
                ->with('error', 'Pesanan tidak ditemukan. Silakan buat pemesanan baru.');
        }

        // Segarkan data ringkasan dari pesanan di database (harga, jadwal, paket)
        $data['package_name'] = $order->package_name;
        $data['package_price'] = 'Rp' . number_format($order->total_price, 0, ',', '.');
        $data['session_date'] = $order->session_date ? Studio::dateLabel($order->session_date) : $data['session_date'];
        $data['session_time'] = $order->session_time_slot;
        $data['lokasi'] = $order->location;

        $payment = $order->latestPayment;
        $live = [
            'payment_status' => $order->payment_status,
            'session_status' => $order->session_status,
            'payment_badge' => match ($order->payment_status) {
                'Terverifikasi' => 'Lunas · Terverifikasi',
                'Bukti terkirim' => 'Bukti terkirim · Menunggu verifikasi',
                'Perlu perbaikan' => 'Bukti perlu diperbaiki',
                'Refund' => 'Refund',
                default => 'Belum dibayar',
            },
            'session_badge' => match ($order->session_status) {
                'Dikonfirmasi' => 'Sesi dikonfirmasi',
                'Selesai' => 'Sesi selesai',
                'Dibatalkan' => 'Pesanan dibatalkan',
                default => 'Menunggu konfirmasi',
            },
            'paid_nominal' => $payment ? 'Rp' . number_format($payment->nominal, 0, ',', '.') : null,
            'paid_date' => $payment && $payment->transfer_date ? Studio::dateLabel($payment->transfer_date, 'j F Y') : null,
            'review_note' => $payment ? $payment->customer_message : null,
            'can_pay' => in_array($order->payment_status, ['Belum dibayar', 'Perlu perbaikan'], true)
                && !in_array($order->session_status, ['Dibatalkan', 'Selesai'], true),
        ];

        return view('checkout', compact('data', 'live'));
    }

    /**
     * Tampilkan form Konfirmasi Pembayaran (hanya untuk pemesan pada sesi yang sama)
     */
    /**
     * Cari pesanan untuk halaman pembayaran: lewat kode + kontak (pelanggan yang kembali)
     * atau lewat sesi pemesanan terakhir.
     */
    private function resolveOrder(Request $request): ?Order
    {
        $kode = trim((string) $request->input('kode', ''));
        $kontak = trim((string) $request->input('kontak', ''));

        if ($kode !== '') {
            $order = Order::with('package')->where('order_code', $kode)->first();
            if ($order && $order->matchesContact($kontak)) {
                return $order;
            }
            // kode tanpa kontak yang cocok: boleh bila sama dengan pesanan di sesi ini
            $sess = session('last_booking');
            if ($order && $sess && ($sess['order_code'] ?? null) === $order->order_code && $kontak === '') {
                return $order;
            }
            return null;
        }

        $sess = session('last_booking');
        if ($sess && isset($sess['order_code'])) {
            return Order::with('package')->where('order_code', $sess['order_code'])->first();
        }

        return null;
    }

    public function showPaymentConfirmation(Request $request)
    {
        $order = $this->resolveOrder($request);

        if (!$order) {
            return redirect()->route('cek-pesanan')
                ->with('error', 'Pesanan tidak ditemukan. Masukkan kode pesanan dan email/WhatsApp yang dipakai saat memesan.');
        }

        $contact = ['kode' => $order->order_code, 'kontak' => $order->customer_email];
        $submittedPayment = session('submitted_payment');

        if (!$submittedPayment) {
            if (in_array($order->session_status, ['Dibatalkan', 'Selesai', 'Diarsipkan'], true)) {
                return redirect()->route('cek-pesanan', $contact)->with('error', 'Pesanan ini tidak lagi menerima pembayaran.');
            }
            if ($order->payment_status === 'Terverifikasi') {
                return redirect()->route('cek-pesanan', $contact)->with('info', 'Pembayaran Anda sudah terverifikasi. Tidak perlu mengunggah bukti lagi.');
            }
            if ($order->payment_status === 'Bukti terkirim') {
                return redirect()->route('cek-pesanan', $contact)->with('info', 'Bukti pembayaran Anda sudah diterima dan sedang diperiksa studio. Mohon tunggu agar tidak ada unggahan ganda.');
            }
        }

        $data = \App\Support\BookingSummary::fromOrder($order);
        $isSubmitted = $submittedPayment !== null;
        $needsFix = $order->payment_status === 'Perlu perbaikan';
        $fixMessage = $needsFix ? optional($order->latestPayment)->customer_message : null;

        return view('konfirmasi-pembayaran', compact('data', 'isSubmitted', 'submittedPayment', 'needsFix', 'fixMessage', 'contact'));
    }

    public function storePaymentConfirmation(Request $request)
    {
        $validated = $request->validate([
            'nama_pengirim' => 'required|string|min:3|max:100',
            'tanggal_transfer' => 'required|date|before_or_equal:today|after_or_equal:' . now()->subDays(60)->toDateString(),
            'bank_sumber' => 'required|string|min:2|max:100',
            'nominal' => 'required|string|max:20',
            'catatan_pembayaran' => 'nullable|string|max:1000',
            'struk_pembayaran' => 'required|file|mimes:jpeg,png,jpg,pdf|max:4096',
        ], [
            'nama_pengirim.required' => 'Nama pengirim wajib diisi.',
            'nama_pengirim.min' => 'Nama pengirim minimal 3 karakter.',
            'tanggal_transfer.required' => 'Tanggal transfer wajib diisi.',
            'tanggal_transfer.before_or_equal' => 'Tanggal transfer tidak boleh di masa depan.',
            'tanggal_transfer.after_or_equal' => 'Tanggal transfer terlalu lama (maksimal 60 hari terakhir).',
            'bank_sumber.required' => 'Bank atau sumber dana wajib diisi.',
            'nominal.required' => 'Nominal transfer wajib diisi.',
            'struk_pembayaran.required' => 'Unggah bukti transfer (JPG, PNG, atau PDF, maksimal 4 MB).',
            'struk_pembayaran.mimes' => 'Bukti transfer harus berformat JPG, PNG, atau PDF.',
            'struk_pembayaran.max' => 'Ukuran bukti transfer maksimal 4 MB.',
            'struk_pembayaran.uploaded' => 'Bukti transfer gagal diunggah. Pastikan ukurannya di bawah 4 MB lalu coba lagi.',
        ]);

        $nominalInt = (int) preg_replace('/\D+/', '', $validated['nominal']);
        if ($nominalInt < 1000) {
            return back()->withInput()->withErrors(['nominal' => 'Nominal transfer tidak valid. Masukkan jumlah yang benar-benar Anda transfer.']);
        }

        $order = $this->resolveOrder($request);
        if (!$order) {
            return redirect()->route('cek-pesanan')
                ->with('error', 'Pesanan tidak ditemukan. Masukkan kode pesanan dan email/WhatsApp yang dipakai saat memesan.');
        }
        $contact = ['kode' => $order->order_code, 'kontak' => $order->customer_email];

        if (in_array($order->session_status, ['Dibatalkan', 'Selesai', 'Diarsipkan'], true)) {
            return redirect()->route('cek-pesanan', $contact)->with('error', 'Pesanan ini tidak lagi menerima pembayaran.');
        }
        if ($order->payment_status === 'Terverifikasi') {
            return redirect()->route('cek-pesanan', $contact)->with('info', 'Pembayaran sudah terverifikasi. Tidak perlu mengunggah bukti lagi.');
        }
        if ($order->payment_status === 'Bukti terkirim') {
            return redirect()->route('cek-pesanan', $contact)->with('info', 'Bukti pembayaran Anda sedang diperiksa studio. Mohon tunggu agar tidak ada unggahan ganda.');
        }

        // Simpan berkas bukti
        $file = $request->file('struk_pembayaran');
        $fileOriginalName = $file->getClientOriginalName();
        $fileExt = strtoupper($file->getClientOriginalExtension());
        $filePath = null;
        $proofBlob = null;

        if (config('filesystems.proof_disk') === 'database') {
            $filePath = 'db';
            $proofBlob = [
                'mime' => $file->getMimeType() ?: 'application/octet-stream',
                'data' => base64_encode(file_get_contents($file->getRealPath())),
            ];
        } else {
            $filePath = $file->store('payments', config('filesystems.proof_disk'));
        }

        do {
            $paymentCode = 'BYR-' . date('Y') . '-' . strtoupper(Str::random(5));
        } while (Payment::where('payment_code', $paymentCode)->exists());

        $wasFix = $order->payment_status === 'Perlu perbaikan';

        $payment = Payment::create([
            'payment_code' => $paymentCode,
            'order_id' => $order->id,
            'sender_name' => $validated['nama_pengirim'],
            'transfer_date' => $validated['tanggal_transfer'],
            'bank_source' => $validated['bank_sumber'],
            'nominal' => $nominalInt,
            'sender_notes' => $validated['catatan_pembayaran'] ?? null,
            'proof_file' => $filePath,
            'file_original_name' => $fileOriginalName,
            'file_extension' => $fileExt,
            'status' => 'Bukti terkirim',
            'internal_review_notes' => $wasFix ? 'Bukti perbaikan dikirim ulang oleh pelanggan.' : null,
        ]);
        if ($proofBlob) {
            $payment->proof()->create($proofBlob);
        }

        // Bukti lama yang diminta diperbaiki tidak lagi menunggu tindakan pelanggan
        $order->update(['payment_status' => 'Bukti terkirim']);

        session(['last_booking' => \App\Support\BookingSummary::fromOrder($order)]);

        return redirect()->route('konfirmasi', $contact)
            ->with('submitted_payment', [
                'nama_pengirim' => $validated['nama_pengirim'],
                'tanggal_transfer' => $validated['tanggal_transfer'],
                'bank_sumber' => $validated['bank_sumber'],
                'nominal' => 'Rp' . number_format($nominalInt, 0, ',', '.'),
                'catatan_pembayaran' => $validated['catatan_pembayaran'] ?? '',
                'file_name' => $fileOriginalName,
                'file_ext' => $fileExt,
            ])
            ->with('success', $wasFix
                ? 'Bukti perbaikan Anda sudah diterima. Studio akan memeriksanya kembali.'
                : 'Bukti Anda sudah diterima oleh studio. Pantau hasil pemeriksaan melalui status pesanan.');
    }
}
