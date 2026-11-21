<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PaymentReuploadTest extends TestCase
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

    private function makeBooking(): Order
    {
        $this->post('/pemesanan', [
            'package_id' => 'prewedding', 'session_date' => '2026-11-22', 'session_time' => '09.00',
            'nama_lengkap' => 'Citra Ameena', 'email' => 'citra@example.com', 'whatsapp' => '0812 5555 6666',
            'lokasi' => 'Taman Kota', 'persetujuan' => 1,
        ])->assertRedirect(route('checkout'));
        return Order::where('customer_email', 'citra@example.com')->first();
    }

    private function proof(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('struk.png', base64_decode(self::PNG));
    }

    private function pay(Order $o, array $over = [])
    {
        return $this->post('/konfirmasi-pembayaran', array_merge([
            'kode' => $o->order_code, 'kontak' => $o->customer_email,
            'nama_pengirim' => 'Citra Ameena', 'tanggal_transfer' => '2026-11-14', 'bank_sumber' => 'BCA',
            'nominal' => 'Rp 2.000.000', 'struk_pembayaran' => $this->proof(),
        ], $over));
    }

    public function test_bukti_wajib_diunggah_dan_divalidasi(): void
    {
        $o = $this->makeBooking();
        $this->pay($o, ['struk_pembayaran' => null])->assertSessionHasErrors('struk_pembayaran');
        $this->pay($o, ['tanggal_transfer' => '2026-12-31'])->assertSessionHasErrors('tanggal_transfer');
        $this->pay($o, ['nominal' => 'abc'])->assertSessionHasErrors('nominal');
        $this->pay($o, ['struk_pembayaran' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('struk_pembayaran');
        $this->assertSame(0, Payment::where('order_id', $o->id)->count());
    }

    public function test_alur_perbaikan_bukti_hingga_unggah_ulang_oleh_pelanggan_yang_kembali(): void
    {
        $o = $this->makeBooking();
        $this->pay($o)->assertRedirect()->assertSessionHas('submitted_payment');
        $this->assertSame('Bukti terkirim', $o->fresh()->payment_status);

        // unggahan ganda saat masih diperiksa ditolak
        $this->pay($o)->assertRedirect()->assertSessionHas('info');
        $this->assertSame(1, Payment::where('order_id', $o->id)->count());

        // admin meminta perbaikan
        $p = Payment::where('order_id', $o->id)->first();
        $this->asAdmin()->post("/admin/pembayaran/{$p->payment_code}/minta-perbaikan", ['alasan' => 'Nominal tidak terbaca', 'pesan' => 'Unggah ulang struk yang lebih jelas'])->assertSessionHas('success');

        // pelanggan KEMBALI tanpa sesi lama: cek pesanan menampilkan permintaan perbaikan, bukan "menunggu pembayaran"
        $this->flushSession();
        $res = $this->get('/cek-pesanan?kode=' . $o->order_code . '&kontak=citra@example.com');
        $res->assertOk()->assertSee('BUKTI PERLU DIPERBAIKI')->assertSee('Unggah ulang struk yang lebih jelas')
            ->assertSee('Unggah ulang bukti pembayaran')->assertDontSee('MENUNGGU PEMBAYARAN');

        // halaman unggah dapat dibuka lewat kode + kontak dan menampilkan pesan admin
        $this->flushSession();
        $form = $this->get('/konfirmasi-pembayaran?kode=' . $o->order_code . '&kontak=citra@example.com');
        $form->assertOk()->assertSee('Studio meminta perbaikan bukti pembayaran')->assertSee('Unggah ulang struk yang lebih jelas');

        // kontak salah tidak diizinkan
        $this->flushSession();
        $this->get('/konfirmasi-pembayaran?kode=' . $o->order_code . '&kontak=salah@example.com')->assertRedirect(route('cek-pesanan'));

        // unggah ulang tanpa sesi lama berhasil
        $this->flushSession();
        $this->pay($o, ['nominal' => '2000000'])->assertRedirect()->assertSessionHas('success');
        $o->refresh();
        $this->assertSame('Bukti terkirim', $o->payment_status);
        $this->assertSame(2, Payment::where('order_id', $o->id)->count());

        // admin melihat bukti baru dengan pratinjau
        $new = Payment::where('order_id', $o->id)->latest('id')->first();
        $this->asAdmin()->get("/admin/pembayaran/{$new->payment_code}")->assertOk()->assertSee("/admin/pembayaran/{$new->payment_code}/bukti", false);
    }

    public function test_status_cek_pesanan_mengikuti_tahap_nyata(): void
    {
        $o = $this->makeBooking();
        $q = '/cek-pesanan?kode=' . $o->order_code . '&kontak=0812 5555 6666';

        $this->get($q)->assertOk()->assertSee('Menunggu transfer pembayaran')->assertSee('Unggah bukti pembayaran');
        $o->update(['payment_status' => 'Terverifikasi']);
        $this->get($q)->assertOk()->assertSee('Pembayaran Anda sudah terverifikasi')->assertDontSee('Contoh tampilan hasil');
        $o->update(['session_status' => 'Dikonfirmasi', 'confirmed_at' => now()]);
        $this->get($q)->assertOk()->assertSee('Sesi Anda sudah dikonfirmasi')->assertSee('Persiapan sesi Anda');
        $o->update(['session_status' => 'Dibatalkan', 'cancel_reason' => 'Berhalangan']);
        $this->get($q)->assertOk()->assertSee('Pesanan ini dibatalkan')->assertSee('Berhalangan');
    }

    public function test_cek_pesanan_tanpa_pencarian_tidak_error(): void
    {
        $this->flushSession();
        $this->get('/cek-pesanan')->assertOk();
        $this->get('/cek-pesanan?kode=TIDAK-ADA&kontak=x@y.z')->assertOk()->assertSee('Pesanan tidak ditemukan');
    }

    public function test_opsi_qris_dihapus_hanya_transfer_bank(): void
    {
        $o = $this->makeBooking();

        $checkout = $this->get('/checkout');
        $checkout->assertOk()->assertSee('Transfer bank')->assertDontSee('QRIS')->assertDontSee('name="metode"', false);

        $form = $this->get('/konfirmasi-pembayaran?kode=' . $o->order_code . '&kontak=citra@example.com&metode=qris');
        $form->assertOk()->assertSee('Transfer Bank')->assertDontSee('QRIS')->assertDontSee('Ubah metode pembayaran')->assertDontSee('scan');

        $this->asAdmin()->get('/admin/pengaturan')->assertOk()->assertDontSee('QRIS');
    }
}
