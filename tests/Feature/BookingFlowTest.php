<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingFlowTest extends TestCase
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

    private function payload(array $over = []): array
    {
        return array_merge([
            'package_id' => 'prewedding',
            'session_date' => '2026-11-21',
            'session_time' => '09.00',
            'nama_lengkap' => 'Tester Satu',
            'email' => 'tester1@example.com',
            'whatsapp' => '+62 812 1111 2222',
            'lokasi' => 'Taman Kota',
            'persetujuan' => 1,
        ], $over);
    }

    public function test_kalender_mengikuti_tanggal_hari_ini_bukan_oktober_statis(): void
    {
        $res = $this->get('/pemesanan?package=prewedding');
        $res->assertOk();
        $res->assertSee('id="calendar-data"', false);
        $res->assertDontSee('data-date="2026-10-17"', false);
        $res->assertSee('"min":"2026-11-15"', false);   // paling cepat besok
        $res->assertSee('"today":"2026-11-14"', false);
    }

    public function test_harga_ringkasan_awal_sesuai_paket_terpilih(): void
    {
        $res = $this->get('/pemesanan?package=graduation');
        $res->assertOk();
        $res->assertSee('id="sumPrice">Rp700.000<', false);
        $res->assertDontSee('Rp9.500.000');
    }

    public function test_pesanan_dibuat_dengan_jam_selesai_sesuai_durasi_paket(): void
    {
        $res = $this->post('/pemesanan', $this->payload());
        $res->assertRedirect(route('checkout'));

        $order = Order::where('customer_email', 'tester1@example.com')->first();
        $this->assertSame('2026-11-21', $order->session_date->toDateString());
        $this->assertSame('09.00–11.00 WIB · 120 menit', $order->session_time_slot);
        $this->assertSame(2000000, $order->total_price);

        $this->get('/checkout')->assertOk()->assertSee('Sabtu, 21 November 2026')->assertSee('Belum dibayar');
    }

    public function test_slot_yang_sudah_dipesan_tidak_bisa_dipesan_lagi(): void
    {
        $this->post('/pemesanan', $this->payload())->assertRedirect(route('checkout'));

        $dup = $this->post('/pemesanan', $this->payload(['email' => 'lain@example.com']));
        $dup->assertSessionHasErrors('session_date');
        $this->assertSame(1, Order::whereDate('session_date', '2026-11-21')->count());
    }

    public function test_slot_bentrok_karena_durasi_juga_ditolak(): void
    {
        // Prewedding 09.00-11.00 -> Graduation (60 menit) 13.00 tetap boleh, 09.00 tidak
        $this->post('/pemesanan', $this->payload())->assertRedirect(route('checkout'));
        $this->post('/pemesanan', $this->payload(['package_id' => 'graduation', 'session_time' => '09.00', 'email' => 'b@example.com']))
            ->assertSessionHasErrors('session_date');
        $this->post('/pemesanan', $this->payload(['package_id' => 'graduation', 'session_time' => '13.00', 'email' => 'c@example.com']))
            ->assertRedirect(route('checkout'));
    }

    public function test_tanggal_lampau_dan_hari_ini_ditolak(): void
    {
        $this->post('/pemesanan', $this->payload(['session_date' => '2026-10-17']))->assertSessionHasErrors('session_date');
        $this->post('/pemesanan', $this->payload(['session_date' => '2026-11-14']))->assertSessionHasErrors('session_date');
        $this->assertSame(0, Order::where('customer_email', 'tester1@example.com')->count());
    }

    public function test_checkout_menampilkan_status_pembayaran_real_time(): void
    {
        $this->post('/pemesanan', $this->payload());
        $order = Order::where('customer_email', 'tester1@example.com')->first();

        $this->get('/checkout')->assertSee('Belum dibayar');

        $order->update(['payment_status' => 'Bukti terkirim']);
        $this->get('/checkout')->assertSee('Bukti terkirim · Menunggu verifikasi');

        $order->update(['payment_status' => 'Terverifikasi']);
        $this->get('/checkout')->assertSee('Lunas · Terverifikasi');
    }

    public function test_validasi_form_pemesanan_menolak_data_wajib_yang_kosong_atau_tidak_valid(): void
    {
        foreach ([
            ['nama_lengkap' => '', 'field' => 'nama_lengkap'],
            ['nama_lengkap' => 'A', 'field' => 'nama_lengkap'],
            ['nama_lengkap' => '12345', 'field' => 'nama_lengkap'],
            ['email' => '', 'field' => 'email'],
            ['email' => 'bukan-email', 'field' => 'email'],
            ['whatsapp' => '', 'field' => 'whatsapp'],
            ['whatsapp' => 'abc', 'field' => 'whatsapp'],
            ['whatsapp' => '12345', 'field' => 'whatsapp'],
            ['lokasi' => '', 'field' => 'lokasi'],
            ['lokasi' => '   ', 'field' => 'lokasi'],
            ['session_date' => '', 'field' => 'session_date'],
            ['session_time' => '', 'field' => 'session_time'],
            ['package_id' => '', 'field' => 'package_id'],
            ['persetujuan' => '', 'field' => 'persetujuan'],
        ] as $case) {
            $field = $case['field'];
            unset($case['field']);
            $this->post('/pemesanan', $this->payload($case))->assertSessionHasErrors($field);
        }
        $this->assertSame(0, Order::where('customer_email', 'tester1@example.com')->count());
    }

    public function test_data_valid_dengan_variasi_format_diterima(): void
    {
        $this->post('/pemesanan', $this->payload(['nama_lengkap' => "Siti Nur'aini-Putri", 'whatsapp' => '+62 812-3456-7890']))->assertRedirect(route('checkout'));
    }
}
