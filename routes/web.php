<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\OrderTrackingController;
use App\Http\Controllers\AdminCustomerController;
use App\Http\Controllers\ClientGalleryController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminGalleryController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\AdminPackageController;
use App\Http\Controllers\AdminScheduleController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AdminPaymentController;
use App\Http\Controllers\AuthController;
use App\Models\Package;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    // Karya terbaru: 3 kolom (masonry), sesuai Figma
    $columns = [
        [
            ['image' => 'work-1.jpg', 'height' => 440, 'title' => 'Graduation', 'category' => 'Wisuda'],
            ['image' => 'work-2.jpg', 'height' => 260, 'title' => 'Family Photoshoot', 'category' => 'Keluarga'],
        ],
        [
            ['image' => 'work-3.jpg', 'height' => 300, 'title' => 'Prewedding', 'category' => 'Prewedding'],
            ['image' => 'work-4.jpg', 'height' => 400, 'title' => 'Family Photoshoot', 'category' => 'Keluarga'],
        ],
        [
            ['image' => 'work-5.jpg', 'height' => 400, 'title' => 'Prewedding', 'category' => 'Prewedding'],
            ['image' => 'work-6.jpg', 'height' => 300, 'title' => 'Birthday', 'category' => 'Ulang Tahun'],
        ],
    ];

    // Galeri terbuka yang sudah diterbitkan menggantikan foto contoh (terbaru lebih dulu)
    $openGalleries = \App\Models\Gallery::query()
        ->where('status', 'Diterbitkan')->where('access_type', 'Terbuka')->whereHas('photos')
        ->latest('id')->take(6)->get();
    $slotOrder = [[0, 0], [1, 0], [2, 0], [0, 1], [1, 1], [2, 1]];
    foreach ($openGalleries as $i => $g) {
        $coverId = \App\Models\GalleryPhoto::where('gallery_id', $g->id)->orderBy('sort_order')->orderBy('id')->value('id');
        [$col, $row] = $slotOrder[$i];
        $columns[$col][$row] = [
            'image' => route('galeri-klien.foto', ['slug' => $g->slug, 'photo' => $coverId]),
            'height' => $columns[$col][$row]['height'],
            'title' => $g->title,
            'category' => 'Galeri',
            'url' => route('galeri-klien', ['slug' => $g->slug]),
        ];
    }

    // Ambil dari database dengan fallback
    $dbPackages = Package::where('is_active', true)->get();
    if ($dbPackages->isNotEmpty()) {
        $packages = $dbPackages->map(function ($pkg) {
            return [
                'id' => $pkg->slug,
                'image' => $pkg->imageSource(),
                'featured' => (bool)$pkg->is_featured,
                'label' => '',
                'name' => $pkg->name,
                'description' => $pkg->description,
                'note' => $pkg->tagline,
                'price' => 'Rp' . number_format($pkg->price, 0, ',', '.'),
                'items' => is_array($pkg->features) ? $pkg->features : [],
            ];
        })->toArray();
    } else {
        $packages = [
            [
                'id' => 'birthday-family-photoshoot',
                'image' => 'paket-1.jpg',
                'featured' => true,
                'label' => '',
                'name' => 'Birthday/Family Photoshoot',
                'description' => '',
                'note' => 'Di luar transport',
                'price' => 'Rp1.250.000',
                'items' => ['Durasi Foto: 2 Jam', '30 Edited Photo', '5 Photo Express', 'All Photo via Gdrive', 'Pengerjaan: 5-7 hari kerja'],
            ],
            [
                'id' => 'prewedding',
                'image' => 'paket-2.jpg',
                'featured' => true,
                'label' => '',
                'name' => 'Prewedding',
                'description' => '',
                'note' => 'Di luar fee transport',
                'price' => 'Rp2.000.000',
                'items' => ['Durasi Foto: 2 Jam', '30 Edited Photo', 'All Photo via Google Drive', 'Pengerjaan: 7-10 hari kerja'],
            ],
            [
                'id' => 'graduation',
                'image' => 'paket-3.jpg',
                'featured' => false,
                'label' => '',
                'name' => 'Graduation',
                'description' => '',
                'note' => 'Di luar transport',
                'price' => 'Rp700.000',
                'items' => ['Durasi Foto: 1 Jam', '15 Edited Photo', 'All Photo (Preview) via Gdrive', 'Unlimited Photo (khusus foto di studio)', 'Pengerjaan: 5-7 hari kerja'],
            ],
        ];
    }

    return view('beranda', compact('columns', 'packages'));
});

// Pemesanan & Alur Checkout
Route::get('/pemesanan', [BookingController::class, 'create'])->name('pemesanan');
Route::post('/pemesanan', [BookingController::class, 'store'])->name('pemesanan.store');
Route::get('/media/paket/{slug}', [AdminPackageController::class, 'image'])->name('package.image');

Route::get('/checkout', [BookingController::class, 'checkout'])->name('checkout');

Route::get('/konfirmasi-pembayaran', [BookingController::class, 'showPaymentConfirmation'])->name('konfirmasi');
Route::post('/konfirmasi-pembayaran', [BookingController::class, 'storePaymentConfirmation'])->name('konfirmasi.store');

// Cek Pesanan
Route::get('/cek-pesanan', [OrderTrackingController::class, 'show'])->name('cek-pesanan');

// Galeri Klien Publik
Route::get('/galeri-klien', [ClientGalleryController::class, 'show'])->name('galeri-klien');
Route::post('/galeri-klien/pin', [ClientGalleryController::class, 'unlock'])->middleware('throttle:30,1')->name('galeri-klien.pin');
Route::get('/galeri-klien/{slug}/foto/{photo}/{variant?}', [ClientGalleryController::class, 'photo'])->whereNumber('photo')->name('galeri-klien.foto');
/*
|--------------------------------------------------------------------------
| Admin Backoffice Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    // Autentikasi Admin (Publik untuk halaman login & proses)
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('admin.login');
    Route::post('/login', [AuthController::class, 'login'])->name('admin.login.submit');
    Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');

    // Seluruh rute dashboard & pengelolaan admin dilindungi middleware 'admin.token'
    Route::middleware('admin.token')->group(function () {
        Route::get('/', function () {
            return redirect()->route('admin.dashboard');
        });

        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

        // Pesanan
        Route::get('/pesanan', [AdminOrderController::class, 'index'])->name('admin.pesanan');
        Route::get('/pesanan/{id}', [AdminOrderController::class, 'show'])->name('admin.pesanan.detail');
        Route::put('/pesanan/{id}', [AdminOrderController::class, 'update'])->name('admin.pesanan.update');
        Route::delete('/pesanan/{id}', [AdminOrderController::class, 'destroy'])->name('admin.pesanan.hapus');
        Route::post('/pesanan/{id}/selesai-sesi', [AdminOrderController::class, 'selesaikanSesi'])->name('admin.pesanan.selesai-sesi');
        Route::post('/pesanan/{id}/konfirmasi-sesi', [AdminOrderController::class, 'konfirmasiSesi'])->name('admin.pesanan.konfirmasi-sesi');
        Route::post('/pesanan/{id}/batalkan-sesi', [AdminOrderController::class, 'batalkanSesi'])->name('admin.pesanan.batalkan-sesi');
        Route::post('/pesanan/{id}/arsipkan-sesi', [AdminOrderController::class, 'arsipkanSesi'])->name('admin.pesanan.arsipkan-sesi');

        // Pembayaran
        Route::get('/pembayaran', [AdminPaymentController::class, 'index'])->name('admin.pembayaran');
        Route::get('/pembayaran/{id}', [AdminPaymentController::class, 'show'])->name('admin.pembayaran.detail');
        Route::get('/pembayaran/{id}/bukti', [AdminPaymentController::class, 'bukti'])->name('admin.pembayaran.bukti');
        Route::post('/pembayaran/manual', [AdminPaymentController::class, 'catatManual'])->name('admin.pembayaran.manual');
        Route::post('/pembayaran/{id}/catatan', [AdminPaymentController::class, 'simpanCatatan'])->name('admin.pembayaran.catatan');
        Route::post('/pembayaran/{id}/verifikasi', [AdminPaymentController::class, 'verifikasi'])->name('admin.pembayaran.verifikasi-action');
        Route::post('/pembayaran/{id}/minta-perbaikan', [AdminPaymentController::class, 'mintaPerbaikan'])->name('admin.pembayaran.minta-perbaikan');

        // Jadwal
        Route::get('/jadwal', [AdminScheduleController::class, 'index'])->name('admin.jadwal');
        Route::post('/jadwal/blokir', [AdminScheduleController::class, 'storeBlock'])->name('admin.jadwal.blokir');
        Route::put('/jadwal/blokir/{id}', [AdminScheduleController::class, 'updateBlock'])->name('admin.jadwal.blokir.update');
        Route::delete('/jadwal/blokir/{id}', [AdminScheduleController::class, 'destroyBlock'])->name('admin.jadwal.blokir.hapus');

        // Pelanggan
        Route::get('/pelanggan', [AdminCustomerController::class, 'index'])->name('admin.pelanggan');
        Route::post('/pelanggan', [AdminCustomerController::class, 'store'])->name('admin.pelanggan.simpan');
        Route::get('/pelanggan/{id}', [AdminCustomerController::class, 'show'])->name('admin.pelanggan.detail');
        Route::put('/pelanggan/{id}', [AdminCustomerController::class, 'update'])->name('admin.pelanggan.update');
        Route::post('/pelanggan/{id}/arsipkan', [AdminCustomerController::class, 'arsipkan'])->name('admin.pelanggan.arsipkan');
        Route::post('/pelanggan/{id}/aktifkan', [AdminCustomerController::class, 'aktifkan'])->name('admin.pelanggan.aktifkan');

        // Paket
        Route::get('/paket', [AdminPackageController::class, 'index'])->name('admin.paket');
        Route::post('/paket', [AdminPackageController::class, 'store'])->name('admin.paket.simpan');
        Route::put('/paket/{id}', [AdminPackageController::class, 'update'])->name('admin.paket.update');
        Route::post('/paket/{id}/toggle', [AdminPackageController::class, 'toggle'])->name('admin.paket.toggle');
        Route::delete('/paket/{id}', [AdminPackageController::class, 'destroy'])->name('admin.paket.hapus');

        // Galeri
        Route::get('/galeri', [AdminGalleryController::class, 'index'])->name('admin.galeri');
        Route::get('/galeri/{id}', [AdminGalleryController::class, 'show'])->whereNumber('id')->name('admin.galeri.lihat');
        Route::post('/galeri/{id}/foto', [AdminGalleryController::class, 'uploadPhoto'])->name('admin.galeri.foto.unggah');
        Route::get('/galeri/{id}/foto/{photo}/{variant?}', [AdminGalleryController::class, 'photoImage'])->name('admin.galeri.foto');
        Route::delete('/galeri/{id}/foto/{photo}', [AdminGalleryController::class, 'deletePhoto'])->name('admin.galeri.foto.hapus');
        Route::post('/galeri/{id}/foto/{photo}/sampul', [AdminGalleryController::class, 'coverPhoto'])->name('admin.galeri.foto.sampul');
        Route::post('/galeri', [AdminGalleryController::class, 'store'])->name('admin.galeri.simpan');
        Route::put('/galeri/{id}', [AdminGalleryController::class, 'update'])->name('admin.galeri.update');
        Route::delete('/galeri/{id}', [AdminGalleryController::class, 'destroy'])->name('admin.galeri.hapus');

        // Pengaturan
        Route::get('/pengaturan', [AdminSettingsController::class, 'index'])->name('admin.pengaturan');
        Route::post('/pengaturan', [AdminSettingsController::class, 'save'])->name('admin.pengaturan.save');
    });
});
