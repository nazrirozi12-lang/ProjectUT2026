<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminOrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.proof_disk' => 'database']);
        Carbon::setTestNow(Carbon::parse('2026-11-14 10:00:00', 'Asia/Jakarta'));
        $this->seed(DatabaseSeeder::class);
        Package::forgetImageCache();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeOrder(array $over = []): Order
    {
        static $n = 100;
        $n++;
        $customer = Customer::firstOrCreate(['email' => "uji{$n}@example.com"], [
            'customer_code' => 'CUST-UJI-' . $n, 'name' => "Pelanggan Uji {$n}", 'whatsapp' => '08123456789', 'status' => 'aktif',
        ]);
        $pkg = Package::where('slug', 'prewedding')->first();

        return Order::create(array_merge([
            'order_code' => "CH-UJI-{$n}",
            'invoice_number' => "INV-UJI-{$n}",
            'customer_id' => $customer->id,
            'package_id' => $pkg->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_whatsapp' => $customer->whatsapp,
            'package_name' => $pkg->name,
            'total_price' => $pkg->price,
            'session_date' => '2026-12-' . str_pad((string) (($n % 25) + 1), 2, '0', STR_PAD_LEFT),
            'session_time_slot' => '09.00–11.00 WIB · 120 menit',
            'location' => 'Studio',
            'session_status' => 'Permintaan',
            'payment_status' => 'Belum dibayar',
        ], $over));
    }

    private function makePayment(Order $o, array $over = []): Payment
    {
        return Payment::create(array_merge([
            'payment_code' => 'BYR-UJI-' . $o->id,
            'order_id' => $o->id,
            'sender_name' => $o->customer_name,
            'transfer_date' => '2026-11-13',
            'bank_source' => 'Bank BCA',
            'nominal' => $o->total_price,
            'status' => 'Bukti terkirim',
        ], $over));
    }

    public function test_halaman_admin_butuh_login(): void
    {
        $this->get('/admin/pesanan')->assertRedirect(route('admin.login'));
    }

    public function test_daftar_pesanan_memakai_hitungan_nyata_dan_pencarian(): void
    {
        $total = Order::where('session_status', '!=', 'Diarsipkan')->count();
        $a = $this->makeOrder(['customer_name' => 'Dzaky Naufal']);
        $this->makeOrder();

        $res = $this->asAdmin()->get('/admin/pesanan');
        $res->assertOk()->assertSee('Semua pesanan (' . ($total + 2) . ')');
        $res->assertSee($a->order_code);
        $res->assertDontSee('Data ilustrasi');

        $this->asAdmin()->get('/admin/pesanan?q=Dzaky')->assertOk()->assertSee('Dzaky Naufal')->assertDontSee('Pelanggan Uji 102');
        $this->asAdmin()->get('/admin/pesanan?q=tidak-ada-sama-sekali')->assertOk()->assertSee('Tidak ada pesanan yang cocok');
    }

    public function test_daftar_pesanan_paginasi(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->makeOrder();
        }
        $this->asAdmin()->get('/admin/pesanan')->assertOk()->assertSee('Berikutnya')->assertSee('Menampilkan 1–10');
        $this->asAdmin()->get('/admin/pesanan?page=2')->assertOk()->assertSee('Menampilkan 11–');
    }

    public function test_alur_konfirmasi_sesi_hanya_setelah_pembayaran_terverifikasi(): void
    {
        $o = $this->makeOrder(['payment_status' => 'Bukti terkirim']);
        $p = $this->makePayment($o);

        // belum terverifikasi -> ditolak
        $this->asAdmin()->post("/admin/pesanan/{$o->order_code}/konfirmasi-sesi")->assertSessionHas('error');
        $this->assertSame('Permintaan', $o->fresh()->session_status);

        // verifikasi pembayaran TIDAK otomatis mengonfirmasi sesi
        $this->asAdmin()->post("/admin/pembayaran/{$p->payment_code}/verifikasi", ['konfirmasi' => 1, 'catatan' => 'ok'])
            ->assertRedirect()->assertSessionHas('success');
        $o->refresh();
        $this->assertSame('Terverifikasi', $o->payment_status);
        $this->assertSame('Permintaan', $o->session_status);

        // sekarang bisa dikonfirmasi, linimasa ikut berubah
        $this->asAdmin()->get("/admin/pesanan/{$o->order_code}")->assertOk()->assertSee('Pembayaran terverifikasi')->assertSee('Konfirmasi sesi');
        $this->asAdmin()->post("/admin/pesanan/{$o->order_code}/konfirmasi-sesi")->assertRedirect()->assertSessionHas('success');
        $this->assertSame('Dikonfirmasi', $o->fresh()->session_status);
        $this->asAdmin()->get("/admin/pesanan/{$o->order_code}")->assertSee('Jadwal dikonfirmasi')->assertSee('Tandai sesi selesai');

        // selesai -> arsip
        $this->asAdmin()->post("/admin/pesanan/{$o->order_code}/selesai-sesi")->assertSessionHas('success');
        $this->assertSame('Selesai', $o->fresh()->session_status);
        $this->asAdmin()->post("/admin/pesanan/{$o->order_code}/arsipkan-sesi")->assertRedirect()->assertSessionHas('success');
        $this->assertSame('Diarsipkan', $o->fresh()->session_status);
    }

    public function test_batalkan_wajib_alasan_dan_melepas_slot(): void
    {
        $o = $this->makeOrder(['session_date' => '2026-12-20']);
        $this->asAdmin()->post("/admin/pesanan/{$o->order_code}/batalkan-sesi", ['alasan' => ''])->assertSessionHasErrors('alasan');
        $this->assertSame('Permintaan', $o->fresh()->session_status);

        $this->assertFalse(\App\Support\Studio::isAvailable('2026-12-20', '09.00', 120));
        $this->asAdmin()->post("/admin/pesanan/{$o->order_code}/batalkan-sesi", ['alasan' => 'Pelanggan batal'])->assertSessionHas('success');
        $this->assertSame('Dibatalkan', $o->fresh()->session_status);
        $this->assertTrue(\App\Support\Studio::isAvailable('2026-12-20', '09.00', 120));
    }

    public function test_arsip_ditolak_untuk_pesanan_aktif(): void
    {
        $o = $this->makeOrder();
        $this->asAdmin()->post("/admin/pesanan/{$o->order_code}/arsipkan-sesi")->assertSessionHas('error');
        $this->assertSame('Permintaan', $o->fresh()->session_status);
    }

    public function test_edit_pesanan_memeriksa_bentrok_jadwal(): void
    {
        $a = $this->makeOrder(['session_date' => '2026-12-21']);
        $b = $this->makeOrder(['session_date' => '2026-12-22']);

        $this->asAdmin()->put("/admin/pesanan/{$b->order_code}", [
            'session_date' => '2026-12-21', 'session_time' => '09.00', 'location' => 'Studio', 'internal_notes' => '',
        ])->assertSessionHasErrors('session_date');

        $this->asAdmin()->put("/admin/pesanan/{$b->order_code}", [
            'session_date' => '2026-12-21', 'session_time' => '13.00', 'location' => 'Taman', 'internal_notes' => 'Bawa properti',
        ])->assertRedirect()->assertSessionHas('success');
        $b->refresh();
        $this->assertSame('2026-12-21', $b->session_date->toDateString());
        $this->assertSame('13.00–15.00 WIB · 120 menit', $b->session_time_slot);
        $this->assertSame('Taman', $b->location);
    }

    public function test_data_tidak_ditemukan_di_admin_mengalihkan_dengan_notifikasi(): void
    {
        $this->asAdmin()->get('/admin/pesanan/TIDAK-ADA')->assertRedirect(route('admin.pesanan'))->assertSessionHas('error');
        $this->asAdmin()->get('/admin/pembayaran/TIDAK-ADA')->assertRedirect(route('admin.pembayaran'))->assertSessionHas('error');
        $this->asAdmin()->get('/admin/halaman-ngawur')->assertRedirect(route('admin.dashboard'))->assertSessionHas('error');
    }

    public function test_detail_pembayaran_menampilkan_dokumen_gambar_unggahan_pelanggan(): void
    {
        $o = $this->makeOrder();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $file = UploadedFile::fake()->createWithContent('struk.png', $png);

        $this->post('/pemesanan', [
            'package_id' => 'prewedding', 'session_date' => '2026-12-05', 'session_time' => '13.00',
            'nama_lengkap' => 'Uji Bukti', 'email' => 'bukti@example.com', 'whatsapp' => '0812',
        ])->assertRedirect(route('checkout'));
        $order = Order::where('customer_email', 'bukti@example.com')->first();

        $this->post('/konfirmasi-pembayaran', [
            'nama_pengirim' => 'Uji Bukti', 'tanggal_transfer' => '2026-11-14', 'bank_sumber' => 'Bank BCA',
            'nominal' => '2000000', 'struk_pembayaran' => $file,
        ])->assertSessionHasNoErrors();

        $payment = Payment::where('order_id', $order->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame('Bukti terkirim', $order->fresh()->payment_status);

        $detail = $this->asAdmin()->get("/admin/pembayaran/{$payment->payment_code}");
        $detail->assertOk()->assertSee('Bukti transfer Uji Bukti')->assertSee("/admin/pembayaran/{$payment->payment_code}/bukti", false);
        $detail->assertSee('Nominal cocok dengan invoice');

        $img = $this->asAdmin()->get("/admin/pembayaran/{$payment->payment_code}/bukti");
        $img->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_minta_perbaikan_dan_pesan_tampil_ke_pelanggan(): void
    {
        $o = $this->makeOrder(['payment_status' => 'Bukti terkirim']);
        $p = $this->makePayment($o);
        $this->asAdmin()->post("/admin/pembayaran/{$p->payment_code}/minta-perbaikan", ['alasan' => 'Nominal tidak sesuai', 'pesan' => 'Unggah ulang struk yang jelas'])
            ->assertSessionHas('success');
        $this->assertSame('Perlu perbaikan', $o->fresh()->payment_status);

        // pelanggan melihat instruksi di checkout
        $this->withSession(['last_booking' => ['order_code' => $o->order_code, 'package_id' => 'prewedding', 'session_date' => '-', 'package_image' => 'paket-2.jpg', 'package_items' => [], 'email' => $o->customer_email, 'nama_lengkap' => 'x', 'whatsapp' => 'x', 'invoice_number' => $o->invoice_number, 'package_desc' => '', 'lokasi_hint' => '', 'catatan' => null]])
            ->get('/checkout')->assertOk()->assertSee('Unggah ulang struk yang jelas');
    }

    public function test_catat_pembayaran_manual_dan_catatan_internal(): void
    {
        $o = $this->makeOrder();
        $this->asAdmin()->post('/admin/pembayaran/manual', ['invoice' => 'INV-TIDAK-ADA', 'metode' => 'Tunai di Kasir Studio', 'nominal' => '100', 'tanggal' => '2026-11-14'])
            ->assertSessionHasErrors('invoice');

        $this->asAdmin()->post('/admin/pembayaran/manual', ['invoice' => $o->invoice_number, 'metode' => 'Tunai di Kasir Studio', 'nominal' => 'Rp 2.000.000', 'tanggal' => '2026-11-14', 'catatan' => 'Diterima di studio'])
            ->assertRedirect()->assertSessionHas('success');
        $o->refresh();
        $this->assertSame('Terverifikasi', $o->payment_status);
        $payment = $o->payments()->first();
        $this->assertSame(2000000, (int) $payment->nominal);

        $this->asAdmin()->post("/admin/pembayaran/{$payment->payment_code}/catatan", ['catatan' => 'Sudah dicek mutasi'])->assertSessionHas('success');
        $this->assertSame('Sudah dicek mutasi', $payment->fresh()->internal_review_notes);
    }

    public function test_daftar_pembayaran_metrik_tab_dan_belum_dibayar(): void
    {
        $o1 = $this->makeOrder(['payment_status' => 'Bukti terkirim']);
        $this->makePayment($o1);
        $o2 = $this->makeOrder(['payment_status' => 'Belum dibayar']);

        $this->asAdmin()->get('/admin/pembayaran')->assertOk()->assertSee('Bukti terkirim (')->assertDontSee('Rp23.500.000');
        $this->asAdmin()->get('/admin/pembayaran?tab=belum-dibayar')->assertOk()->assertSee($o2->order_code);
        $this->asAdmin()->get('/admin/pembayaran?tab=bukti-terkirim&q=' . $o1->customer_name)->assertOk()->assertSee('BYR-UJI-' . $o1->id);
    }
}
