<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Package;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StudioSlot;
use App\Models\Gallery;
use App\Models\GalleryPhoto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Admin User (password dari env ADMIN_PASSWORD; wajib di production)
        $adminPassword = env('ADMIN_PASSWORD');
        if (!$adminPassword) {
            if (app()->environment('production')) {
                throw new \RuntimeException('Set ADMIN_PASSWORD sebelum menjalankan seeder di production.');
            }
            $adminPassword = 'password123'; // hanya untuk lokal
        }

        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@narocapture.com')],
            [
                'name' => 'Naro Capture Admin',
                'password' => Hash::make($adminPassword),
            ]
        );

        // 2. Packages
        // Paket Naro Capture (kode PKG-* dipertahankan agar data lama ikut terbarui)
        $pkgPotret = Package::updateOrCreate(
            ['code' => 'PKG-POTRET'],
            [
                'slug' => 'birthday-family-photoshoot',
                'name' => 'Birthday/Family Photoshoot',
                'tagline' => 'Di luar transport',
                'description' => null,
                'price' => 1250000,
                'duration' => '2 jam',
                'duration_minutes' => 120,
                'photo_count' => 30,
                'photo_count_label' => '30 edited photo + 5 photo express',
                'features' => [
                    'Durasi Foto: 2 Jam',
                    '30 Edited Photo',
                    '5 Photo Express',
                    'All Photo via Gdrive',
                    'Pengerjaan: 5-7 hari kerja',
                ],
                'badge' => 'Populer',
                'is_active' => true,
                'is_featured' => true,
            ]
        );

        $pkgKeluarga = Package::updateOrCreate(
            ['code' => 'PKG-KELUARGA'],
            [
                'slug' => 'prewedding',
                'name' => 'Prewedding',
                'tagline' => 'Di luar fee transport',
                'description' => null,
                'price' => 2000000,
                'duration' => '2 jam',
                'duration_minutes' => 120,
                'photo_count' => 30,
                'photo_count_label' => '30 edited photo',
                'features' => [
                    'Durasi Foto: 2 Jam',
                    '30 Edited Photo',
                    'All Photo via Google Drive',
                    'Pengerjaan: 7-10 hari kerja',
                ],
                'badge' => 'Pilihan Favorit',
                'is_active' => true,
                'is_featured' => true,
            ]
        );

        $pkgEditorial = Package::updateOrCreate(
            ['code' => 'PKG-EDITORIAL'],
            [
                'slug' => 'graduation',
                'name' => 'Graduation',
                'tagline' => 'Di luar transport',
                'description' => null,
                'price' => 700000,
                'duration' => '1 jam',
                'duration_minutes' => 60,
                'photo_count' => 15,
                'photo_count_label' => '15 edited photo',
                'features' => [
                    'Durasi Foto: 1 Jam',
                    '15 Edited Photo',
                    'All Photo (Preview) via Gdrive',
                    'Unlimited Photo (khusus foto di studio)',
                    'Pengerjaan: 5-7 hari kerja',
                ],
                'badge' => 'Graduation',
                'is_active' => true,
                'is_featured' => false,
            ]
        );

        // 3. Customers
        $customerAyu = Customer::updateOrCreate(
            ['email' => 'ayu.pramesti@example.com'],
            [
                'customer_code' => 'CUST-2026-001',
                'name' => 'Ayu Pramesti',
                'whatsapp' => '081234567890',
                'notes' => 'Pelanggan tetap sesi personal, prefer tone hangat & filmic.',
                'status' => 'aktif',
            ]
        );

        $customerBudi = Customer::updateOrCreate(
            ['email' => 'budi.santoso@example.com'],
            [
                'customer_code' => 'CUST-2026-002',
                'name' => 'Budi Santoso & Keluarga',
                'whatsapp' => '081398765432',
                'notes' => 'Sesi tahunan bersama anak kembar usia 5 tahun.',
                'status' => 'aktif',
            ]
        );

        // 4. Orders
        $order1 = Order::updateOrCreate(
            ['order_code' => 'CH-CONTOH-001'],
            [
                'invoice_number' => 'INV-CH-2026-001',
                'customer_id' => $customerAyu->id,
                'package_id' => $pkgPotret->id,
                'customer_name' => $customerAyu->name,
                'customer_email' => $customerAyu->email,
                'customer_whatsapp' => $customerAyu->whatsapp,
                'package_name' => $pkgPotret->name,
                'total_price' => $pkgPotret->price,
                'session_date' => '2026-10-18',
                'session_time_slot' => '10.00–11.00 WIB',
                'location' => 'Studio Utama (Studio A)',
                'customer_notes' => 'Meminta nuansa warm neutral dengan wardrobe krem/putih.',
                'internal_notes' => 'Konfirmasi via WA 9 Okt. Klien request soft background.',
                'session_status' => 'Permintaan',
                'payment_status' => 'Bukti terkirim',
            ]
        );

        $order2 = Order::updateOrCreate(
            ['order_code' => 'CH-CONTOH-002'],
            [
                'invoice_number' => 'INV-CH-2026-002',
                'customer_id' => $customerBudi->id,
                'package_id' => $pkgKeluarga->id,
                'customer_name' => $customerBudi->name,
                'customer_email' => $customerBudi->email,
                'customer_whatsapp' => $customerBudi->whatsapp,
                'package_name' => $pkgKeluarga->name,
                'total_price' => $pkgKeluarga->price,
                'session_date' => '2026-10-24',
                'session_time_slot' => '14.00–15.30 WIB',
                'location' => 'Studio Keluarga (Studio B)',
                'customer_notes' => 'Bawa 2 set pakaian, butuh bangku anak tambahan.',
                'internal_notes' => 'Sudah DP lunas, siapkan properti mainan kayu.',
                'session_status' => 'Dikonfirmasi',
                'payment_status' => 'Terverifikasi',
            ]
        );

        // 5. Payments
        Payment::updateOrCreate(
            ['payment_code' => 'BYR-CONTOH-001'],
            [
                'order_id' => $order1->id,
                'sender_name' => 'Ayu Pramesti',
                'transfer_date' => '2026-10-09',
                'bank_source' => 'BCA',
                'nominal' => $pkgPotret->price,
                'sender_notes' => 'Transfer via m-BCA jam 10:15 WIB. Mohon verifikasi.',
                'proof_file' => 'struk-bca-ch001.jpg',
                'file_original_name' => 'bukti_transfer_ayu_bca.jpg',
                'file_extension' => 'JPG',
                'status' => 'Bukti terkirim',
                'internal_review_notes' => 'Bukti cocok dengan nominal paket, menunggu pengecekan mutasi BCA.',
                'verified_at' => null,
            ]
        );

        Payment::updateOrCreate(
            ['payment_code' => 'BYR-CONTOH-002'],
            [
                'order_id' => $order2->id,
                'sender_name' => 'Budi Santoso',
                'transfer_date' => '2026-10-08',
                'bank_source' => 'Mandiri',
                'nominal' => $pkgKeluarga->price,
                'sender_notes' => 'Transfer lunas penuh paket keluarga.',
                'proof_file' => 'struk-mandiri-ch002.jpg',
                'file_original_name' => 'mandiri_transfer_budi.pdf',
                'file_extension' => 'PDF',
                'status' => 'Terverifikasi',
                'internal_review_notes' => 'Sudah diverifikasi via mutasi rekening Mandiri Studio.',
                'verified_at' => '2026-10-08 14:20:00',
            ]
        );

        // 6. Studio Slots
        StudioSlot::updateOrCreate(
            ['date' => '2026-10-18', 'start_time' => '10:00:00'],
            [
                'end_time' => '11:00:00',
                'type' => 'booked',
                'order_id' => $order1->id,
                'note' => 'Birthday/Family Photoshoot - Ayu Pramesti',
            ]
        );

        StudioSlot::updateOrCreate(
            ['date' => '2026-10-24', 'start_time' => '14:00:00'],
            [
                'end_time' => '15:30:00',
                'type' => 'booked',
                'order_id' => $order2->id,
                'note' => 'Prewedding - Budi Santoso',
            ]
        );

        StudioSlot::updateOrCreate(
            ['date' => '2026-10-20', 'start_time' => '09:00:00'],
            [
                'end_time' => '13:00:00',
                'type' => 'blocked',
                'order_id' => null,
                'note' => 'Kalibrasi Lampu Godox & Pengecatan Dinding Studio A',
            ]
        );

        // 7. Gallery
        $gallery = Gallery::updateOrCreate(
            ['slug' => 'galeri-ayu-pramesti'],
            [
                'title' => 'Galeri Sesi Potret Ayu Pramesti',
                'order_id' => $order1->id,
                'client_name' => 'Ayu Pramesti',
                'cover_image' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=800&q=80',
                'target_photos' => 20,
                'status' => 'Diterbitkan',
                'access_type' => 'Privat',
                'pin_code' => '1234',
            ]
        );

        GalleryPhoto::updateOrCreate(
            ['gallery_id' => $gallery->id, 'sort_order' => 1],
            ['photo_path' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=800&q=80', 'caption' => 'Warm tone portrait']
        );
        GalleryPhoto::updateOrCreate(
            ['gallery_id' => $gallery->id, 'sort_order' => 2],
            ['photo_path' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?auto=format&fit=crop&w=800&q=80', 'caption' => 'Black & white classic']
        );
    }
}
