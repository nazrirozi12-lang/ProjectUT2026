<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\StudioSlot;
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
            $imageMap = [
                'birthday-family-photoshoot' => 'paket-1.jpg',
                'prewedding' => 'paket-2.jpg',
                'graduation' => 'paket-3.jpg',
            ];

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
                'image' => $imageMap[$pkg->slug] ?? 'paket-1.jpg',
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

        return view('pemesanan', compact('packages', 'selectedPackageId'));
    }

    /**
     * Proses penyimpanan pemesanan baru ke database
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'package_id' => 'required|string',
            'session_date' => 'nullable|string',
            'session_time' => 'nullable|string',
            'nama_lengkap' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'whatsapp' => 'required|string|max:50',
            'lokasi' => 'nullable|string|max:255',
            'catatan' => 'nullable|string',
        ]);

        $packageSlug = $validated['package_id'];
        $package = Package::where('slug', $packageSlug)->first();

        $imageMap = [
            'birthday-family-photoshoot' => 'paket-1.jpg',
            'prewedding' => 'paket-2.jpg',
            'graduation' => 'paket-3.jpg',
        ];

        $packageName = $package ? $package->name : ucwords(str_replace('-', ' ', $packageSlug));
        $packagePrice = $package ? $package->price : 9500000;
        $packagePriceFormatted = 'Rp' . number_format($packagePrice, 0, ',', '.');
        $packageImage = $imageMap[$packageSlug] ?? 'paket-1.jpg';
        $packageDesc = $package ? (string) $package->description : '';
        $packageItems = $package && is_array($package->features) ? $package->features : [
            'Sesi foto studio terkurasi',
            'File resolusi tinggi via cloud pribadi',
            'Izin cetak pribadi'
        ];
        $lokasiHint = 'Lokasi sesi dibahas saat perencanaan. Biaya transport di luar harga paket.';

        // 1. Simpan atau perbarui Customer
        $customer = Customer::firstOrNew(['email' => $validated['email']]);
        if (!$customer->exists) {
            $customer->customer_code = 'CUST-' . date('Y') . '-' . strtoupper(Str::random(4));
        }
        $customer->fill([
            'name' => $validated['nama_lengkap'],
            'whatsapp' => $validated['whatsapp'],
            'status' => 'aktif',
        ])->save();

        // 2. Generate Nomor Unik
        do {
            $suffix = strtoupper(Str::random(5));
            $orderCode = 'CH-' . date('ymd') . '-' . $suffix;
        } while (Order::where('order_code', $orderCode)->exists());
        $invoiceNumber = 'INV-CH-' . date('Y') . '-' . $suffix;

        // Tanggal sesi
        $rawSessionDate = $validated['session_date'] ?? date('Y-m-d', strtotime('+7 days'));
        // Konversi ke format Y-m-d bila dalam string bahasa
        $sessionDateSql = date('Y-m-d', strtotime($rawSessionDate) ?: strtotime('+7 days'));

        $rawTimeSlot = ($validated['session_time'] ?? '15.30') . '–17.00 WIB · 90 menit';

        // 3. Simpan Order ke Database
        $order = Order::create([
            'order_code' => $orderCode,
            'invoice_number' => $invoiceNumber,
            'customer_id' => $customer->id,
            'package_id' => $package ? $package->id : null,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_whatsapp' => $customer->whatsapp,
            'package_name' => $packageName,
            'total_price' => $packagePrice,
            'session_date' => $sessionDateSql,
            'session_time_slot' => $rawTimeSlot,
            'location' => $validated['lokasi'] ?? 'Studio Utama Naro Capture',
            'customer_notes' => $validated['catatan'] ?? null,
            'session_status' => 'Permintaan',
            'payment_status' => 'Belum dibayar',
        ]);

        // Simpan juga ke session untuk aliran UX instan tanpa login
        $bookingData = [
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'invoice_number' => $order->invoice_number,
            'package_id' => $packageSlug,
            'package_name' => $order->package_name,
            'package_price' => $packagePriceFormatted,
            'package_raw_price' => $packagePrice,
            'package_image' => $packageImage,
            'package_desc' => $packageDesc,
            'package_items' => $packageItems,
            'lokasi_hint' => $lokasiHint,
            'session_date' => $validated['session_date'] ?? date('l, d F Y', strtotime($sessionDateSql)),
            'session_time' => $rawTimeSlot,
            'nama_lengkap' => $order->customer_name,
            'email' => $order->customer_email,
            'whatsapp' => $order->customer_whatsapp,
            'lokasi' => $order->location,
            'catatan' => $order->customer_notes,
        ];

        session(['last_booking' => $bookingData]);

        return redirect()->route('checkout');
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

        return view('checkout', compact('data'));
    }

    /**
     * Tampilkan form Konfirmasi Pembayaran (hanya untuk pemesan pada sesi yang sama)
     */
    public function showPaymentConfirmation(Request $request)
    {
        $data = session('last_booking');

        if (!$data) {
            return redirect()->route('pemesanan')
                ->with('error', 'Sesi pemesanan tidak ditemukan. Silakan isi formulir pemesanan terlebih dahulu.');
        }

        $metode = $request->query('metode', 'transfer');
        $submittedPayment = session('submitted_payment');
        $isSubmitted = $request->query('status') === 'success' || $submittedPayment !== null;

        return view('konfirmasi-pembayaran', compact('data', 'metode', 'isSubmitted', 'submittedPayment'));
    }

    /**
     * Simpan bukti transfer pembayaran ke storage & database
     */
    public function storePaymentConfirmation(Request $request)
    {
        $validated = $request->validate([
            'nama_pengirim' => 'required|string|max:255',
            'tanggal_transfer' => 'required|date',
            'bank_sumber' => 'required|string|max:100',
            'nominal' => 'required|string',
            'catatan_pembayaran' => 'nullable|string',
            'struk_pembayaran' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:4096',
        ]);

        $orderData = session('last_booking');
        $order = null;

        if ($orderData && isset($orderData['order_code'])) {
            $order = Order::where('order_code', $orderData['order_code'])->first();
        }

        if (!$order) {
            return redirect()->route('pemesanan')
                ->with('error', 'Sesi pemesanan tidak ditemukan. Silakan buat pemesanan terlebih dahulu.');
        }

        // Simpan berkas bukti bila ada
        $filePath = null;
        $proofBlob = null; // dipakai bila PAYMENT_PROOF_DISK=database
        $fileOriginalName = 'bukti-transfer.pdf';
        $fileExt = 'PDF';

        if ($request->hasFile('struk_pembayaran')) {
            $file = $request->file('struk_pembayaran');
            $fileOriginalName = $file->getClientOriginalName();
            $fileExt = strtoupper($file->getClientOriginalExtension());

            if (config('filesystems.proof_disk') === 'database') {
                $filePath = 'db';
                $proofBlob = [
                    'mime' => $file->getMimeType() ?: 'application/octet-stream',
                    'data' => base64_encode(file_get_contents($file->getRealPath())),
                ];
            } else {
                $filePath = $file->store('payments', config('filesystems.proof_disk'));
            }
        }

        // Parse nominal number
        $rawNominal = preg_replace('/[^0-9]/', '', $validated['nominal']);
        $nominalInt = (int)$rawNominal ?: ($order ? $order->total_price : 9500000);

        do {
            $paymentCode = 'BYR-' . date('Y') . '-' . strtoupper(Str::random(5));
        } while (Payment::where('payment_code', $paymentCode)->exists());

        if ($order) {
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
            ]);

            if ($proofBlob) {
                $payment->proof()->create($proofBlob);
            }

            // Update status pesanan
            $order->update([
                'payment_status' => 'Bukti terkirim',
            ]);
        }

        $submittedData = [
            'nama_pengirim' => $validated['nama_pengirim'],
            'tanggal_transfer' => $validated['tanggal_transfer'],
            'bank_sumber' => $validated['bank_sumber'],
            'nominal' => 'Rp' . number_format($nominalInt, 0, ',', '.'),
            'catatan_pembayaran' => $validated['catatan_pembayaran'] ?? '',
            'file_name' => $fileOriginalName,
            'file_ext' => $fileExt,
        ];

        session(['submitted_payment' => $submittedData]);

        return redirect()->route('konfirmasi', ['status' => 'success'])
            ->with('success', 'Bukti Anda sudah diterima oleh studio. Pantau hasil pemeriksaan melalui status pesanan.');
    }
}
