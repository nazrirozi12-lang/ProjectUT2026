<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Package;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerPagesTest extends TestCase
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

    public function test_halaman_publik_dapat_dibuka(): void
    {
        foreach (['/', '/pemesanan', '/cek-pesanan', '/konfirmasi-pembayaran', '/galeri-klien'] as $url) {
            $res = $this->get($url);
            $this->assertContains($res->getStatusCode(), [200, 302], "{$url} gagal: " . $res->getStatusCode());
        }
    }

    public function test_beranda_tidak_memakai_slider_untuk_tiga_paket_atau_kurang(): void
    {
        $this->get('/')->assertOk()->assertDontSee('packages--scroll', false)
            ->assertSee('Birthday/Family Photoshoot')->assertSee('Graduation')->assertSee('Di luar transport')
            ->assertSee('BEBERAPA CERITA YANG TELAH KAMI ABADIKAN')
            ->assertDontSee('FOTOGRAFI LIFESTYLE')->assertDontSee('Kumpulan potret keluarga');
    }

    public function test_cek_pesanan_menampilkan_data_paket_dan_tanggal_indonesia(): void
    {
        $this->post('/pemesanan', [
            'package_id' => 'graduation', 'session_date' => '2026-11-18', 'session_time' => '13.00',
            'nama_lengkap' => 'Cek Pesanan', 'email' => 'cek@example.com', 'whatsapp' => '081211112222',
        ])->assertRedirect(route('checkout'));
        $o = Order::where('customer_email', 'cek@example.com')->first();

        $res = $this->get('/cek-pesanan?kode=' . $o->order_code . '&kontak=cek@example.com');
        $res->assertOk()->assertSee('Rabu, 18 November 2026')->assertSee('Durasi Foto: 1 Jam')->assertSee('Rp700.000');
        $res->assertDontSee('Dua lokasi dan dua ganti kostum');
    }

    public function test_pencarian_tidak_peka_huruf_besar_kecil(): void
    {
        $this->asAdmin()->get('/admin/pesanan?q=AYU')->assertOk()->assertSee('Ayu Pramesti');
        $this->asAdmin()->get('/admin/pelanggan?q=budi')->assertOk()->assertSee('Budi');
    }
}
