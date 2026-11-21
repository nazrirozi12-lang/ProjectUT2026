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

class GalleryFlowTest extends TestCase
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

    private function gallery(string $access = 'Privat', string $status = 'Diterbitkan'): Gallery
    {
        static $n = 700;
        $n++;
        $c = Customer::create(['customer_code' => "CUST-G{$n}", 'name' => "Farah {$n}", 'email' => "g{$n}@example.com", 'whatsapp' => '0812', 'status' => 'Aktif']);
        $p = Package::where('slug', 'graduation')->first();
        $o = Order::create([
            'order_code' => "CH-G-{$n}", 'invoice_number' => "INV-G-{$n}", 'customer_id' => $c->id, 'package_id' => $p->id,
            'customer_name' => $c->name, 'customer_email' => $c->email, 'customer_whatsapp' => '0812', 'package_name' => $p->name,
            'total_price' => $p->price, 'session_date' => '2026-11-10', 'session_time_slot' => '09.00–10.00 WIB · 60 menit',
            'location' => 'Studio', 'session_status' => 'Selesai', 'payment_status' => 'Terverifikasi',
        ]);
        return Gallery::create([
            'slug' => "potret-farah-{$n}", 'title' => "Potret Farah {$n}", 'order_id' => $o->id, 'client_name' => $c->name,
            'target_photos' => 10, 'status' => $status, 'access_type' => $access, 'pin_code' => $access === 'Privat' ? '482913' : null, 'photo_count' => 0,
        ]);
    }

    private function upload(Gallery $g, string $name = 'a.png')
    {
        return $this->asAdmin()->withCredentials()->postJson("/admin/galeri/{$g->id}/foto", [
            'foto' => UploadedFile::fake()->createWithContent($name, base64_decode(self::PNG)),
            'thumb' => UploadedFile::fake()->createWithContent('t.png', base64_decode(self::PNG)),
        ]);
    }

    public function test_admin_dapat_mengunggah_melihat_menjadikan_sampul_dan_menghapus_foto(): void
    {
        $g = $this->gallery();
        $this->upload($g, 'satu.png')->assertOk()->assertJson(['ok' => true, 'count' => 1]);
        $this->upload($g, 'dua.png')->assertOk()->assertJson(['count' => 2]);
        $this->assertSame(2, $g->fresh()->photo_count);

        $page = $this->asAdmin()->get("/admin/galeri/{$g->id}");
        $page->assertOk()->assertSee('Foto galeri (2)')->assertSee("/admin/galeri/{$g->id}/foto/", false)->assertSee('482913');

        $first = $g->photos()->orderBy('id')->first();
        $second = $g->photos()->orderBy('id')->skip(1)->first();
        $this->asAdmin()->get("/admin/galeri/{$g->id}/foto/{$first->id}")->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->asAdmin()->post("/admin/galeri/{$g->id}/foto/{$second->id}/sampul")->assertSessionHas('success');
        $this->assertSame($second->id, $g->photos()->orderBy('sort_order')->orderBy('id')->first()->id);

        $this->asAdmin()->delete("/admin/galeri/{$g->id}/foto/{$first->id}")->assertSessionHas('success');
        $this->assertSame(1, $g->fresh()->photo_count);
    }

    public function test_unggah_foto_menolak_berkas_bukan_gambar(): void
    {
        $g = $this->gallery();
        $this->asAdmin()->withCredentials()->postJson("/admin/galeri/{$g->id}/foto", ['foto' => UploadedFile::fake()->create('x.pdf', 20)])->assertStatus(422);
        $this->assertSame(0, $g->photos()->count());
    }

    public function test_galeri_privat_wajib_pin_dan_foto_tidak_bisa_diakses_tanpa_pin(): void
    {
        $g = $this->gallery('Privat');
        $this->upload($g);
        $photo = $g->photos()->first();
        $this->flushSession();

        $this->get("/galeri-klien?slug={$g->slug}")->assertOk()->assertSee('Masukkan PIN galeri')->assertDontSee('Foto 01');
        $this->get("/galeri-klien/{$g->slug}/foto/{$photo->id}")->assertNotFound();

        $this->post('/galeri-klien/pin', ['slug' => $g->slug, 'pin' => '000000'])->assertSessionHasErrors('pin');
        $this->get("/galeri-klien/{$g->slug}/foto/{$photo->id}")->assertNotFound();

        $this->post('/galeri-klien/pin', ['slug' => $g->slug, 'pin' => '482913'])->assertRedirect(route('galeri-klien', ['slug' => $g->slug]));
        $this->get("/galeri-klien?slug={$g->slug}")->assertOk()->assertSee('Foto 01')->assertSee($g->title);
        $this->get("/galeri-klien/{$g->slug}/foto/{$photo->id}/full")->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_pin_dibatasi_percobaannya(): void
    {
        $g = $this->gallery('Privat');
        for ($i = 0; $i < 5; $i++) {
            $this->post('/galeri-klien/pin', ['slug' => $g->slug, 'pin' => '11111' . $i])->assertSessionHasErrors('pin');
        }
        // percobaan ke-6 dikunci walau PIN benar
        $this->post('/galeri-klien/pin', ['slug' => $g->slug, 'pin' => '482913'])->assertSessionHasErrors('pin');
        $this->get("/galeri-klien?slug={$g->slug}")->assertSee('Terlalu banyak percobaan');
    }

    public function test_galeri_belum_terbit_atau_tidak_ada_menampilkan_halaman_tidak_ditemukan(): void
    {
        $draft = $this->gallery('Terbuka', 'Draf');
        $this->get("/galeri-klien?slug={$draft->slug}")->assertNotFound()->assertSee('Galeri tidak ditemukan');
        $this->get('/galeri-klien?slug=tidak-ada')->assertNotFound();
        $this->get('/galeri-klien')->assertRedirect(route('cek-pesanan'));
    }

    public function test_galeri_terbuka_tampil_di_karya_terbaru_dan_privat_tidak(): void
    {
        $open = $this->gallery('Terbuka');
        $priv = $this->gallery('Privat');
        $this->upload($open);
        $this->upload($priv);

        $home = $this->get('/');
        $home->assertOk()->assertSee($open->title)->assertSee("/galeri-klien?slug={$open->slug}", false);
        $home->assertDontSee($priv->title);

        // galeri terbuka dapat dilihat tanpa PIN
        $this->flushSession();
        $this->get("/galeri-klien?slug={$open->slug}")->assertOk()->assertSee('Foto 01');
    }

    public function test_cek_pesanan_menampilkan_tombol_buka_galeri_setelah_diterbitkan(): void
    {
        $g = $this->gallery('Privat');
        $o = $g->order;
        $url = '/cek-pesanan?kode=' . $o->order_code . '&kontak=' . $o->customer_email;
        $this->get($url)->assertOk()->assertSee('Buka galeri foto')->assertSee("/galeri-klien?slug={$g->slug}", false);

        $g->update(['status' => 'Siap']);
        $this->get($url)->assertOk()->assertDontSee('Buka galeri foto');
    }

    public function test_menerbitkan_galeri_boleh_dengan_foto_tanpa_tautan_drive(): void
    {
        $g = $this->gallery('Privat', 'Draf');
        $payload = ['title' => $g->title, 'status' => 'Diterbitkan', 'access_type' => 'Privat', 'target_photos' => 10, 'photo_count' => 0, 'drive_url' => ''];
        $this->asAdmin()->put("/admin/galeri/{$g->id}", $payload)->assertSessionHasErrors('drive_url');
        $this->upload($g);
        $this->asAdmin()->put("/admin/galeri/{$g->id}", $payload)->assertSessionHas('success');
        $this->assertSame('Diterbitkan', $g->fresh()->status);
    }
}
