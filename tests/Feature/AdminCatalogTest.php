<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Gallery;
use App\Models\Order;
use App\Models\Package;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

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

    private function order(array $o = []): Order
    {
        static $n = 900;
        $n++;
        $c = Customer::create(['customer_code' => "CUST-K{$n}", 'name' => "Klien Katalog {$n}", 'email' => "k{$n}@example.com", 'whatsapp' => '0812', 'status' => 'Aktif']);
        $p = Package::where('slug', 'prewedding')->first();
        return Order::create(array_merge([
            'order_code' => "CH-K-{$n}", 'invoice_number' => "INV-K-{$n}", 'customer_id' => $c->id, 'package_id' => $p->id,
            'customer_name' => $c->name, 'customer_email' => $c->email, 'customer_whatsapp' => '0812', 'package_name' => $p->name,
            'total_price' => $p->price, 'session_date' => '2026-11-20', 'session_time_slot' => '09.00–11.00 WIB · 120 menit',
            'location' => 'Studio', 'session_status' => 'Dikonfirmasi', 'payment_status' => 'Terverifikasi',
        ], $o));
    }

    // ---------- Galeri ----------
    public function test_buat_galeri_dari_pesanan_terkonfirmasi_masuk_tabel_sebagai_draf(): void
    {
        $ok = $this->order(['customer_name' => 'Nadia Galeri']);
        $notYet = $this->order(['session_status' => 'Permintaan']);

        $this->asAdmin()->get('/admin/galeri')->assertOk()->assertSee($ok->order_code)->assertDontSee($notYet->order_code);

        $res = $this->asAdmin()->post('/admin/galeri', ['title' => 'Potret Nadia', 'order_id' => $ok->id, 'target_photos' => 25, 'access_type' => 'Privat']);
        $res->assertRedirect()->assertSessionHas('success');

        $g = Gallery::where('order_id', $ok->id)->first();
        $this->assertSame('Draf', $g->status);
        $this->assertSame('Nadia Galeri', $g->client_name);
        $this->assertSame(6, strlen((string) $g->pin_code));

        $this->asAdmin()->get('/admin/galeri?tab=draf')->assertOk()->assertSee('Potret Nadia')->assertSee('Draf (');
        // pesanan yang sudah punya galeri tidak muncul lagi
        $this->asAdmin()->get('/admin/galeri')->assertDontSee('value="' . $ok->id . '"', false);
    }

    public function test_galeri_tidak_dibuat_untuk_pesanan_yang_belum_dikonfirmasi(): void
    {
        $o = $this->order(['session_status' => 'Permintaan']);
        $this->asAdmin()->post('/admin/galeri', ['title' => 'X', 'order_id' => $o->id, 'access_type' => 'Privat'])->assertSessionHasErrors('order_id');
        $this->assertSame(0, Gallery::where('order_id', $o->id)->count());
    }

    public function test_menerbitkan_galeri_wajib_tautan_drive_dan_bisa_dihapus(): void
    {
        $o = $this->order();
        $this->asAdmin()->post('/admin/galeri', ['title' => 'Prewed', 'order_id' => $o->id, 'access_type' => 'Privat']);
        $g = Gallery::where('order_id', $o->id)->first();
        $payload = ['title' => 'Prewed', 'status' => 'Diterbitkan', 'access_type' => 'Privat', 'target_photos' => 30, 'photo_count' => 30, 'drive_url' => ''];

        $this->asAdmin()->put("/admin/galeri/{$g->id}", $payload)->assertSessionHasErrors('drive_url');
        $this->assertSame('Draf', $g->fresh()->status);

        $this->asAdmin()->put("/admin/galeri/{$g->id}", array_merge($payload, ['notes' => 'ok', 'drive_url' => 'https://drive.google.com/drive/folders/abc']))
            ->assertSessionHas('success');
        $this->assertSame('Diterbitkan', $g->fresh()->status);

        $this->asAdmin()->delete("/admin/galeri/{$g->id}")->assertSessionHas('success');
        $this->assertNull(Gallery::find($g->id));
    }

    // ---------- Pelanggan ----------
    public function test_daftar_dan_detail_pelanggan_memakai_data_nyata(): void
    {
        $o = $this->order(['customer_name' => 'Dzaky Pelanggan']);
        $c = $o->customer;

        $this->asAdmin()->get('/admin/pelanggan')->assertOk()->assertSee('Aktif (')->assertSee($c->customer_code)->assertDontSee('ilustrasi');
        $this->asAdmin()->get('/admin/pelanggan?q=Katalog')->assertOk()->assertSee($c->customer_code);

        $detail = $this->asAdmin()->get("/admin/pelanggan/{$c->customer_code}");
        $detail->assertOk()->assertSee($o->order_code)->assertSee('Riwayat pesanan')->assertSee('wa.me/62812', false);
    }

    public function test_tambah_edit_arsip_pelanggan(): void
    {
        $this->asAdmin()->post('/admin/pelanggan', ['name' => 'Baru Pelanggan', 'email' => 'baru@example.com', 'whatsapp' => '0811223344'])->assertSessionHas('success');
        $c = Customer::where('email', 'baru@example.com')->first();
        $this->assertSame('Aktif', $c->status);

        $this->asAdmin()->post('/admin/pelanggan', ['name' => 'Dobel', 'email' => 'baru@example.com', 'whatsapp' => '08'])->assertSessionHasErrors('email');

        $this->asAdmin()->put("/admin/pelanggan/{$c->customer_code}", ['name' => 'Baru Diubah', 'email' => 'baru@example.com', 'whatsapp' => '0811', 'notes' => 'VIP'])->assertSessionHas('success');
        $this->assertSame('Baru Diubah', $c->fresh()->name);

        $this->asAdmin()->post("/admin/pelanggan/{$c->customer_code}/arsipkan")->assertRedirect()->assertSessionHas('success');
        $this->assertSame('Diarsipkan', $c->fresh()->status);
        $this->asAdmin()->get('/admin/pelanggan?tab=diarsipkan')->assertSee($c->customer_code);
        $this->asAdmin()->post("/admin/pelanggan/{$c->customer_code}/aktifkan")->assertSessionHas('success');
        $this->assertSame('Aktif', $c->fresh()->status);
    }

    public function test_pelanggan_dengan_pesanan_aktif_tidak_bisa_diarsipkan(): void
    {
        $o = $this->order();
        $this->asAdmin()->post("/admin/pelanggan/{$o->customer->customer_code}/arsipkan")->assertSessionHas('error');
        $this->assertSame('Aktif', $o->customer->fresh()->status);
    }

    // ---------- Paket ----------
    public function test_paket_admin_tampil_dari_database_dan_bisa_diedit(): void
    {
        $this->asAdmin()->get('/admin/paket')->assertOk()->assertSee('Prewedding')->assertSee('Rp2.000.000')->assertDontSee('Potret Pribadi');

        $p = Package::where('slug', 'prewedding')->first();
        $this->asAdmin()->put("/admin/paket/{$p->id}", [
            'name' => 'Prewedding Plus', 'price' => 'Rp 2.500.000', 'duration_minutes' => 150,
            'features' => "Durasi Foto: 2,5 Jam\n40 Edited Photo", 'tagline' => 'Di luar transport', 'is_active' => 1,
        ])->assertRedirect(route('admin.paket'))->assertSessionHas('success');

        $p->refresh();
        $this->assertSame('Prewedding Plus', $p->name);
        $this->assertSame(2500000, $p->price);
        $this->assertSame('2 jam 30 menit', $p->duration);
        $this->assertSame(['Durasi Foto: 2,5 Jam', '40 Edited Photo'], $p->features);

        // tampil di halaman pelanggan
        $this->get('/')->assertSee('Prewedding Plus')->assertSee('Rp2.500.000');
        $this->get('/pemesanan')->assertSee('Prewedding Plus');
    }

    public function test_tambah_paket_ke_empat_membuat_slider_beranda_dan_foto_unggahan(): void
    {
        $file = UploadedFile::fake()->createWithContent('foto.png', base64_decode(self::PNG));
        $this->asAdmin()->post('/admin/paket', [
            'name' => 'Potret Wisuda', 'price' => '700000', 'duration_minutes' => 45,
            'features' => "Durasi Foto: 45 Menit\n10 Edited Photo", 'is_active' => 1, 'image' => $file,
        ])->assertRedirect()->assertSessionHas('success');

        $p = Package::where('name', 'Potret Wisuda')->first();
        $this->assertNotNull($p);
        $this->assertSame('potret-wisuda', $p->slug);

        $home = $this->get('/');
        $home->assertSee('Potret Wisuda')->assertSee('packages--scroll', false)->assertSee('/media/paket/potret-wisuda', false);
        $this->get('/media/paket/potret-wisuda')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/pemesanan?package=potret-wisuda')->assertOk()->assertSee('id="sumPrice">Rp700.000<', false);
    }

    public function test_nonaktifkan_dan_hapus_paket(): void
    {
        $used = Package::where('slug', 'prewedding')->first();
        $this->order(); // pesanan memakai prewedding
        $this->asAdmin()->delete("/admin/paket/{$used->id}")->assertSessionHas('error');
        $this->assertNotNull(Package::find($used->id));

        $this->asAdmin()->post("/admin/paket/{$used->id}/toggle")->assertSessionHas('success');
        $this->get('/')->assertDontSee('Rp2.000.000');

        $this->asAdmin()->post('/admin/paket', ['name' => 'Sementara', 'price' => '100000', 'duration_minutes' => 30, 'features' => 'Satu', 'is_active' => 1]);
        $tmp = Package::where('name', 'Sementara')->first();
        $this->asAdmin()->delete("/admin/paket/{$tmp->id}")->assertSessionHas('success');
        $this->assertNull(Package::find($tmp->id));
    }

    public function test_validasi_paket(): void
    {
        $this->asAdmin()->post('/admin/paket', ['name' => '', 'price' => '', 'duration_minutes' => 5, 'features' => ''])
            ->assertSessionHasErrors(['name', 'price', 'duration_minutes', 'features']);
    }

    // ---------- Pengaturan ----------
    public function test_pengaturan_profil_tersimpan_dan_dimuat_kembali(): void
    {
        $this->asAdmin()->post('/admin/pengaturan', [
            'studio_name' => 'Naro Capture Studio', 'admin_name' => 'Nazri', 'studio_email' => 'halo@example.com',
            'studio_whatsapp' => '081234', 'studio_location' => 'Jakarta Selatan', 'timezone' => 'WIB',
        ])->assertRedirect(route('admin.pengaturan'))->assertSessionHas('success');

        $this->asAdmin()->get('/admin/pengaturan')->assertOk()->assertSee('Naro Capture Studio')->assertSee('halo@example.com')->assertSee('Jakarta Selatan');
        $this->asAdmin()->post('/admin/pengaturan', ['studio_name' => '', 'admin_name' => 'x', 'studio_email' => 'bukan-email', 'timezone' => 'WIB'])
            ->assertSessionHasErrors(['studio_name', 'studio_email']);
    }

    public function test_semua_halaman_admin_dapat_dibuka(): void
    {
        foreach (['dashboard', 'pesanan', 'pembayaran', 'jadwal', 'pelanggan', 'paket', 'galeri', 'pengaturan'] as $page) {
            $this->asAdmin()->get("/admin/{$page}")->assertOk();
        }
    }

    public function test_data_sesi_paket_buatan_admin_konsisten_dari_pemesanan_hingga_detail_admin(): void
    {
        $this->asAdmin()->post('/admin/paket', [
            'name' => 'Potret Wisuda', 'price' => '700000', 'duration_minutes' => 45,
            'features' => "Durasi Foto: 45 Menit\n10 Edited Photo", 'tagline' => 'Di luar transport', 'is_active' => 1,
        ])->assertSessionHas('success');

        // halaman pemesanan memakai durasi, harga, dan fasilitas yang diinput admin
        $page = $this->get('/pemesanan?package=potret-wisuda');
        $page->assertOk()->assertSee('data-duration="45"', false)->assertSee('Rp700.000')->assertSee('Durasi Foto: 45 Menit')->assertSee('Di luar transport');
        $this->assertStringContainsString('"45":{"2026-11-15"', $page->getContent());

        // pesanan menyimpan jadwal sesuai durasi 45 menit, dan semua halaman menampilkan data yang sama
        $this->post('/pemesanan', [
            'package_id' => 'potret-wisuda', 'session_date' => '2026-11-24', 'session_time' => '13.00',
            'nama_lengkap' => 'Wisuda Satu', 'email' => 'wisuda@example.com', 'whatsapp' => '0812 7777 8888',
            'lokasi' => 'Kampus', 'persetujuan' => 1,
        ])->assertRedirect(route('checkout'));
        $order = Order::where('customer_email', 'wisuda@example.com')->first();
        $this->assertSame('13.00–13.45 WIB · 45 menit', $order->session_time_slot);
        $this->assertSame(700000, $order->total_price);

        $this->get('/checkout')->assertSee('13.00–13.45 WIB · 45 menit')->assertSee('Rp700.000')->assertSee('Selasa, 24 November 2026');
        $this->asAdmin()->get("/admin/pesanan/{$order->order_code}")->assertSee('13.00–13.45 WIB · 45 menit')->assertSee('Rp700.000')->assertSee('Selasa, 24 November 2026');
        $this->asAdmin()->get('/admin/jadwal?bulan=2026-11&tanggal=2026-11-24')->assertSee('Wisuda Satu')->assertSee('13.00–13.45 WIB');

        // jadwal 45 menit ini menutup slot 13.00 tetapi tidak slot 15.30
        $this->assertFalse(\App\Support\Studio::isAvailable('2026-11-24', '13.00', 45));
        $this->assertTrue(\App\Support\Studio::isAvailable('2026-11-24', '15.30', 45));
    }
}
