<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\StudioSlot;
use App\Support\Studio;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardScheduleTest extends TestCase
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

    private function order(array $o = []): Order
    {
        static $n = 500;
        $n++;
        $c = Customer::create(['customer_code' => "CUST-J{$n}", 'name' => "Klien Jadwal {$n}", 'email' => "j{$n}@example.com", 'whatsapp' => '0812', 'status' => 'aktif']);
        $p = Package::where('slug', 'graduation')->first();
        return Order::create(array_merge([
            'order_code' => "CH-J-{$n}", 'invoice_number' => "INV-J-{$n}", 'customer_id' => $c->id, 'package_id' => $p->id,
            'customer_name' => $c->name, 'customer_email' => $c->email, 'customer_whatsapp' => '0812', 'package_name' => $p->name,
            'total_price' => $p->price, 'session_date' => '2026-11-20', 'session_time_slot' => '15.30–16.30 WIB · 60 menit',
            'location' => 'Studio', 'session_status' => 'Dikonfirmasi', 'payment_status' => 'Terverifikasi',
        ], $o));
    }

    public function test_dashboard_menampilkan_angka_nyata(): void
    {
        $this->order(['session_status' => 'Permintaan', 'payment_status' => 'Bukti terkirim']);
        $res = $this->asAdmin()->get('/admin/dashboard');
        $res->assertOk()->assertSee('Selamat pagi')->assertSee('Sabtu, 14 November 2026')->assertSee('Pesanan November');
        $res->assertDontSee('Ayu Pramesti · Cerita Keluarga')->assertDontSee('ilustrasi');
        $thisMonth = Order::whereYear('created_at', 2026)->whereMonth('created_at', 11)->count();
        $this->assertGreaterThan(0, $thisMonth);
    }

    public function test_dashboard_prioritas_bukti_menunggu(): void
    {
        $o = $this->order(['session_status' => 'Permintaan', 'payment_status' => 'Bukti terkirim']);
        Payment::create(['payment_code' => 'BYR-J-1', 'order_id' => $o->id, 'sender_name' => 'Pengirim Uji', 'transfer_date' => '2026-11-13', 'nominal' => $o->total_price, 'status' => 'Bukti terkirim']);
        $this->asAdmin()->get('/admin/dashboard')->assertOk()->assertSee('Tinjau bukti Pengirim Uji')->assertSee('bukti pembayaran perlu ditinjau');
    }

    public function test_kalender_admin_mengikuti_bulan_dan_bisa_pindah_bulan(): void
    {
        $this->order();
        $h = fn ($t) => '<h2 class="admin-calendar-month-heading">' . $t . '</h2>';
        $this->asAdmin()->get('/admin/jadwal')->assertOk()->assertSee($h('November 2026'), false)->assertDontSee($h('Oktober 2026'), false);
        $this->asAdmin()->get('/admin/jadwal?bulan=2026-12')->assertOk()->assertSee($h('Desember 2026'), false);
        $this->asAdmin()->get('/admin/jadwal?bulan=2027-01')->assertOk()->assertSee($h('Januari 2027'), false);
    }

    public function test_klik_tanggal_menampilkan_sesi_dan_slot(): void
    {
        $o = $this->order(['customer_name' => 'Maya Tester']);
        $res = $this->asAdmin()->get('/admin/jadwal?bulan=2026-11&tanggal=2026-11-20');
        $res->assertOk()->assertSee('Jumat, 20 November 2026')->assertSee('Maya Tester')->assertSee($o->order_code);
        $res->assertSee('Terpesan')->assertSee('Tersedia');
        $this->asAdmin()->get('/admin/jadwal?tanggal=2026-11-25')->assertSee('Belum ada sesi pada tanggal ini');
    }

    public function test_blokir_studio_menutup_slot_di_halaman_pemesanan(): void
    {
        $res = $this->asAdmin()->post('/admin/jadwal/blokir', ['tanggal' => '2026-11-25', 'cakupan' => 'hari', 'catatan' => 'Libur']);
        $res->assertRedirect()->assertSessionHas('success');
        $this->assertFalse(Studio::isAvailable('2026-11-25', '09.00', 120));

        $this->post('/pemesanan', [
            'package_id' => 'prewedding', 'session_date' => '2026-11-25', 'session_time' => '09.00',
            'nama_lengkap' => 'Xavier', 'email' => 'x@example.com', 'whatsapp' => '0812 3456 7890', 'lokasi' => 'Studio', 'persetujuan' => 1,
        ])->assertSessionHasErrors('session_date');

        // blokir jam tertentu hanya menutup slot yang beririsan
        $this->asAdmin()->post('/admin/jadwal/blokir', ['tanggal' => '2026-11-26', 'cakupan' => 'jam', 'mulai' => '13:00', 'selesai' => '15:00']);
        $this->assertTrue(Studio::isAvailable('2026-11-26', '09.00', 120));
        $this->assertFalse(Studio::isAvailable('2026-11-26', '13.00', 60));
    }

    public function test_edit_dan_hapus_blokir(): void
    {
        $this->asAdmin()->post('/admin/jadwal/blokir', ['tanggal' => '2026-11-27', 'cakupan' => 'hari']);
        $b = StudioSlot::where('type', 'blocked')->whereDate('date', '2026-11-27')->first();

        $this->asAdmin()->put("/admin/jadwal/blokir/{$b->id}", ['cakupan' => 'jam', 'mulai' => '09:00', 'selesai' => '11:00', 'catatan' => 'Renovasi'])->assertSessionHas('success');
        $this->assertTrue(Studio::isAvailable('2026-11-27', '13.00', 60));
        $this->assertFalse(Studio::isAvailable('2026-11-27', '09.00', 60));

        $this->asAdmin()->delete("/admin/jadwal/blokir/{$b->id}")->assertSessionHas('success');
        $this->assertTrue(Studio::isAvailable('2026-11-27', '09.00', 60));
    }

    public function test_blokir_tanggal_lampau_ditolak(): void
    {
        $this->asAdmin()->post('/admin/jadwal/blokir', ['tanggal' => '2026-11-01', 'cakupan' => 'hari'])->assertSessionHasErrors('tanggal');
        $this->asAdmin()->post('/admin/jadwal/blokir', ['tanggal' => '2026-11-28', 'cakupan' => 'jam', 'mulai' => '12:00', 'selesai' => '10:00'])->assertSessionHasErrors('selesai');
    }
}
