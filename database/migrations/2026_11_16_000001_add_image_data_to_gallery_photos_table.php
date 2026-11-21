<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Foto galeri disimpan di database (base64) karena filesystem Vercel tidak persisten. */
    public function up(): void
    {
        Schema::table('gallery_photos', function (Blueprint $table) {
            $table->string('mime', 100)->nullable();
            $table->string('file_name')->nullable();
            $table->longText('data')->nullable();   // gambar utama (sudah dikecilkan di browser)
            $table->longText('thumb')->nullable();  // gambar kecil untuk grid
        });
    }

    public function down(): void
    {
        Schema::table('gallery_photos', function (Blueprint $table) {
            $table->dropColumn(['mime', 'file_name', 'data', 'thumb']);
        });
    }
};
